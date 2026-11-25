<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\ProductCategory;
use App\Enums\UnitStatus;
use App\Exceptions\InvalidUnitTransition;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Support\Rupiah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Invoices for a lead: item lines with prices, a status, and the lead turning into a purchase when paid. */
class InvoiceService
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param  array<string, mixed>  $input
     * @return array<string, mixed> */
    public function validate(array $input): array
    {
        return Validator::make($input, self::rules(), [
            'items.required' => 'Invoice minimal berisi satu item.',
            'items.min' => 'Invoice minimal berisi satu item.',
        ])->after(function ($validator) use ($input) {
            if (! $validator->errors()->isEmpty()) {
                return;
            }
            foreach (self::itemProblems($input['items'] ?? []) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        })->validate();
    }

    /**
     * Every main product sold needs the inventory unit (ID) it comes from; accessories carry none.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, string> Error message per "items.N.unit_id".
     */
    public static function itemProblems(array $items): array
    {
        $problems = [];
        $seen = [];

        foreach (array_values($items) as $i => $line) {
            $product = Product::find($line['product_id'] ?? null);
            $key = "items.$i.unit_id";
            $unitId = $line['unit_id'] ?? null;

            if (! $product) {
                continue;
            }

            if ($product->category !== ProductCategory::Main) {
                if ($unitId) {
                    $problems[$key] = "{$product->name} adalah aksesoris dan tidak memakai ID inventori.";
                }

                continue;
            }

            if ((int) ($line['quantity'] ?? 1) !== 1) {
                $problems["items.$i.quantity"] = "{$product->name} dijual per unit: jumlahnya 1 untuk setiap ID inventori.";
            }

            $unit = $unitId ? Unit::find($unitId) : null;
            if (! $unit) {
                $problems[$key] = "Pilih ID inventori untuk {$product->name}.";
            } elseif (isset($seen[$unit->id])) {
                $problems[$key] = "Unit {$unit->code} dipilih dua kali.";
            } elseif ($message = self::unitProblem($unit, $product)) {
                $problems[$key] = $message;
            }
            $seen[$unit?->id] = true;
        }

        return $problems;
    }

    /** Why this unit cannot be sold as this product, or null when it can. */
    public static function unitProblem(Unit $unit, Product $product): ?string
    {
        if (! $product->unit_type_id) {
            return "{$product->name} belum dihubungkan ke jenis unit inventori (menu Produk).";
        }
        if ($unit->unit_type_id !== $product->unit_type_id) {
            return "Unit {$unit->code} bukan jenis {$product->name}.";
        }
        if ($unit->status !== UnitStatus::Available) {
            return "Unit {$unit->code} berstatus {$unit->status->label()}, bukan Tersedia. Unit yang sedang dipinjam dikembalikan dulu.";
        }
        $reserved = InvoiceItem::where('unit_id', $unit->id)
            ->whereHas('invoice', fn ($q) => $q->where('status', InvoiceStatus::Unpaid->value))->exists();

        return $reserved ? "Unit {$unit->code} sudah ada di invoice lain yang belum dibayar." : null;
    }

    /** Units that can be sold as this main product right now (inventory ID picker). @return array<int, string> */
    public static function availableUnits(?Product $product): array
    {
        if (! $product?->unit_type_id) {
            return [];
        }

        return Unit::query()->where('unit_type_id', $product->unit_type_id)->where('status', UnitStatus::Available->value)
            ->whereNotIn('id', InvoiceItem::query()->whereNotNull('unit_id')
                ->whereHas('invoice', fn ($q) => $q->where('status', InvoiceStatus::Unpaid->value))->select('unit_id'))
            ->orderBy('code')->get()->mapWithKeys(fn (Unit $u) => [$u->id => $u->code.' ('.$u->location.')'])->all();
    }

    /** @param  array<int, array<string, mixed>>  $items  product_id, quantity, unit_price.
     */
    public function createForLead(Lead $lead, array $items, User $by, ?string $notes = null): Invoice
    {
        if (! $by->isAdmin() && ! ($by->isSales() && $lead->sales_id === $by->id)) {
            throw ValidationException::withMessages(['items' => 'Anda tidak boleh membuat invoice untuk lead ini.']);
        }

        if ($problems = self::itemProblems($items)) {
            throw ValidationException::withMessages($problems);
        }

        return DB::transaction(function () use ($lead, $items, $by, $notes) {
            $invoice = Invoice::create([
                'lead_id' => $lead->id,
                'created_by' => $by->id,
                'status' => InvoiceStatus::Unpaid,
                'issued_on' => today(),
                'notes' => $notes,
            ]);

            $total = 0;
            foreach ($items as $line) {
                $product = Product::findOrFail($line['product_id']);
                $invoice->items()->create([
                    'product_id' => $product->id,
                    'unit_id' => $product->category === ProductCategory::Main ? ($line['unit_id'] ?? null) : null,
                    'name' => $product->name,
                    'unit_price' => (int) $line['unit_price'],
                    'quantity' => (int) $line['quantity'],
                ]);
                $total += (int) $line['unit_price'] * (int) $line['quantity'];
            }
            $invoice->update(['total' => $total]);

            ActivityLog::record($invoice, 'invoice.created', $by, null, InvoiceStatus::Unpaid->value, Rupiah::format($total));

            return $invoice->refresh();
        });
    }

    /** Admin confirms payment or cancels. Paid and cancelled invoices are final. */
    public function changeStatus(Invoice $invoice, InvoiceStatus $to, User $by): Invoice
    {
        if (! $by->isAdmin()) {
            throw ValidationException::withMessages(['status' => 'Hanya admin yang mengubah status invoice.']);
        }

        return DB::transaction(function () use ($invoice, $to, $by) {
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            $from = $invoice->status;

            if ($from !== InvoiceStatus::Unpaid || $to === InvoiceStatus::Unpaid) {
                throw ValidationException::withMessages(['status' => 'Invoice yang sudah lunas atau batal tidak bisa diubah lagi.']);
            }

            $invoice->update(['status' => $to, 'paid_on' => $to === InvoiceStatus::Paid ? today() : null]);
            ActivityLog::record($invoice, 'invoice.status_changed', $by, $from->value, $to->value);

            if ($to === InvoiceStatus::Paid) {
                // The physical units leave the warehouse: Tersedia -> Terjual, logged on each unit.
                foreach ($invoice->items()->whereNotNull('unit_id')->with('unit')->get() as $item) {
                    try {
                        app(UnitStatusService::class)->transition($item->unit, UnitStatus::Sold, $by, "Dijual lewat invoice {$invoice->code}.");
                    } catch (InvalidUnitTransition) {
                        throw ValidationException::withMessages(['status' => "Unit {$item->unit->code} tidak lagi Tersedia, invoice belum bisa dilunasi. Batalkan dan buat invoice baru dengan unit lain."]);
                    }
                }

                $lead = $invoice->lead;
                if ($lead->status !== LeadStatus::Purchased) {
                    $leadFrom = $lead->status;
                    $lead->update(['status' => LeadStatus::Purchased, 'cancel_reason' => null]);
                    ActivityLog::record($lead, 'lead.status_changed', $by, $leadFrom->value, LeadStatus::Purchased->value, "Invoice {$invoice->code} lunas.");
                }
            }

            return $invoice->refresh();
        });
    }
}

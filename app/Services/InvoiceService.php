<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
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
        ])->validate();
    }

    /** @param  array<int, array<string, mixed>>  $items  product_id, quantity, unit_price.
     */
    public function createForLead(Lead $lead, array $items, User $by, ?string $notes = null): Invoice
    {
        if (! $by->isAdmin() && ! ($by->isSales() && $lead->sales_id === $by->id)) {
            throw ValidationException::withMessages(['items' => 'Anda tidak boleh membuat invoice untuk lead ini.']);
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

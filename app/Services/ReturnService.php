<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\UnitPhotoStage;
use App\Enums\UnitStatus;
use App\Exceptions\LoanNotReturnable;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Records a unit coming back (spec S4). Incomplete components or damage force
 * the unit to "needs service" and notify the admins.
 */
class ReturnService
{
    public function __construct(private readonly UnitStatusService $unitStatus) {}

    /**
     * @param  array<int, array{component: string, is_complete: bool, notes?: ?string}>  $checks
     * @param  array<int, string>  $photoPaths  stored paths of the return photos
     * @param  string  $requestedStatus  "available" or "needs_service", as chosen by the person returning
     */
    public function process(
        Loan $loan,
        User $actor,
        array $checks,
        array $photoPaths,
        ?string $notes,
        bool $hasDamage,
        string $requestedStatus,
    ): ReturnResult {
        return DB::transaction(function () use ($loan, $actor, $checks, $photoPaths, $notes, $hasDamage, $requestedStatus) {
            // Lock the loan so a double tap or two devices cannot return it twice.
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->getKey());

            if (! $loan->isActive()) {
                throw new LoanNotReturnable('Peminjaman ini sudah dikembalikan.');
            }

            $missing = collect($checks)->where('is_complete', false)->pluck('component')->values()->all();
            $needsService = $missing !== [] || $hasDamage || $requestedStatus === UnitStatus::NeedsService->value;
            $target = $needsService ? UnitStatus::NeedsService : UnitStatus::Available;

            foreach ($checks as $check) {
                $loan->returnChecks()->create([
                    'component' => $check['component'],
                    'is_complete' => $check['is_complete'],
                    'notes' => $check['notes'] ?? null,
                ]);
            }

            foreach ($photoPaths as $path) {
                $loan->photos()->create([
                    'unit_id' => $loan->unit_id,
                    'stage' => UnitPhotoStage::Return,
                    'file_path' => $path,
                ]);
            }

            $loan->update([
                'status' => LoanStatus::Returned,
                'returned_at' => now(),
                'returned_by_id' => $actor->id,
                'return_notes' => $notes,
            ]);

            $reasons = array_filter([
                $missing !== [] ? 'Kelengkapan kurang: '.implode(', ', $missing) : null,
                $hasDamage ? 'Ada kerusakan' : null,
                ! $missing && ! $hasDamage && $requestedStatus === UnitStatus::NeedsService->value ? 'Dipilih perlu servis' : null,
            ]);

            $unit = $this->unitStatus->transition(
                $loan->unit,
                $target,
                $actor,
                $reasons ? implode('. ', $reasons).'.' : 'Kembali lengkap, kondisi baik.',
                'loan.returned',
            );

            ActivityLog::record($loan, 'loan.returned', $actor, LoanStatus::Active->value, LoanStatus::Returned->value, $notes);

            if ($needsService) {
                $this->notifyAdmins($loan, $reasons, $notes);
            }

            return new ReturnResult($loan, $unit, $target, $reasons);
        });
    }

    /** @param  array<int, string>  $reasons */
    private function notifyAdmins(Loan $loan, array $reasons, ?string $notes): void
    {
        $admins = User::query()->where('role', 'admin')->where('is_active', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $notification = Notification::make()
            ->title("Unit {$loan->unit->code} perlu servis")
            ->body(trim(implode('. ', $reasons).($notes ? " Catatan: {$notes}" : '')))
            ->danger()
            ->actions([
                Action::make('view')->label('Lihat unit')->url(route('filament.admin.resources.units.view', ['record' => $loan->unit_id]))->markAsRead(),
            ])
            ->toDatabase();

        // notifyNow, not notify(): Filament's database notification is queued, and a service alert
        // must not depend on a queue worker being up.
        foreach ($admins as $admin) {
            $admin->notifyNow($notification);
        }
    }
}

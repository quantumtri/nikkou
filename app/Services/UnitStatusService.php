<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Exceptions\InvalidUnitTransition;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for unit status changes (spec section 5). Every change
 * is validated against the allowed transitions and written to the audit log.
 */
class UnitStatusService
{
    /** @var array<string, list<UnitStatus>> */
    private const TRANSITIONS = [
        'available' => [UnitStatus::OnLoan, UnitStatus::NeedsService, UnitStatus::Sold],
        'on_loan' => [UnitStatus::Available, UnitStatus::NeedsService],
        'needs_service' => [UnitStatus::InService, UnitStatus::Available],
        'in_service' => [UnitStatus::Available, UnitStatus::NeedsService],
        'sold' => [],
    ];

    public function canTransition(UnitStatus $from, UnitStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from->value], true);
    }

    /** Only an available unit can be picked for a new loan without an admin override. */
    public function isLoanable(Unit $unit): bool
    {
        return $unit->status === UnitStatus::Available;
    }

    public function transition(Unit $unit, UnitStatus $to, ?User $actor = null, ?string $notes = null, string $action = 'unit.status_changed'): Unit
    {
        return DB::transaction(function () use ($unit, $to, $actor, $notes, $action) {
            // Lock the row so two devices cannot move the same unit at once.
            $unit = Unit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $from = $unit->status;

            if (! $this->canTransition($from, $to)) {
                throw InvalidUnitTransition::between($from, $to);
            }

            $unit->status = $to;
            $unit->save();

            ActivityLog::record($unit, $action, $actor, $from->value, $to->value, $notes);

            return $unit;
        });
    }

    /**
     * Admin-only escape hatch (e.g. lending a unit still recorded as on loan).
     * Skips the transition table but always requires and logs a reason.
     */
    public function forceTransition(Unit $unit, UnitStatus $to, User $admin, string $reason, string $action = 'unit.status_overridden'): Unit
    {
        abort_unless($admin->isAdmin(), 403);

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Alasan override wajib diisi.');
        }

        return DB::transaction(function () use ($unit, $to, $admin, $reason, $action) {
            $unit = Unit::query()->lockForUpdate()->findOrFail($unit->getKey());
            $from = $unit->status;

            $unit->status = $to;
            $unit->save();

            ActivityLog::record($unit, $action, $admin, $from->value, $to->value, $reason);

            return $unit;
        });
    }
}

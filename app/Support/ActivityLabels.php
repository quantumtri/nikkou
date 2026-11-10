<?php

namespace App\Support;

use App\Enums\UnitStatus;
use App\Models\ActivityLog;

/** Indonesian wording for audit log entries shown in timelines. */
class ActivityLabels
{
    private const ACTIONS = [
        'unit.created' => 'Masuk gudang',
        'unit.updated' => 'Data diubah',
        'unit.status_changed' => 'Status berubah',
        'unit.status_overridden' => 'Override admin',
        'unit.label_printed' => 'Label dicetak',
        'loan.created' => 'Dipinjam',
        'loan.returned' => 'Pengembalian',
    ];

    public static function action(ActivityLog $log): string
    {
        return self::ACTIONS[$log->action] ?? $log->action;
    }

    public static function description(ActivityLog $log): string
    {
        $parts = [];

        if ($log->from && $log->to) {
            $from = UnitStatus::tryFrom($log->from)?->label() ?? $log->from;
            $to = UnitStatus::tryFrom($log->to)?->label() ?? $log->to;
            $parts[] = "{$from} menjadi {$to}.";
        }

        if ($log->notes) {
            $parts[] = $log->notes;
        }

        return implode(' ', $parts) ?: self::action($log);
    }
}

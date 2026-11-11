<?php

namespace App\Services;

use App\Enums\UnitStatus;
use App\Models\Loan;
use App\Models\Unit;

final class ReturnResult
{
    /** @param  array<int, string>  $reasons */
    public function __construct(
        public readonly Loan $loan,
        public readonly Unit $unit,
        public readonly UnitStatus $status,
        public readonly array $reasons,
    ) {}

    public function needsService(): bool
    {
        return $this->status === UnitStatus::NeedsService;
    }
}

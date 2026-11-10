<?php

namespace App\Exceptions;

use App\Models\Loan;
use App\Models\Unit;
use DomainException;

class UnitNotLoanable extends DomainException
{
    public function __construct(public readonly Unit $unit, public readonly ?Loan $activeLoan = null)
    {
        parent::__construct(self::describe($unit, $activeLoan));
    }

    public static function describe(Unit $unit, ?Loan $activeLoan): string
    {
        if ($activeLoan) {
            $since = $activeLoan->starts_on->locale('id')->translatedFormat('j M');

            return "{$unit->code} masih tercatat dipinjam {$activeLoan->borrowerName()} sejak {$since}. Catat pengembalian lebih dulu.";
        }

        return "{$unit->code} berstatus {$unit->status->label()} dan tidak bisa dipinjamkan.";
    }
}

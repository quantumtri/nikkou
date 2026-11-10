<?php

namespace App\Exceptions;

use App\Enums\UnitStatus;
use DomainException;

class InvalidUnitTransition extends DomainException
{
    public static function between(UnitStatus $from, UnitStatus $to): self
    {
        return new self("Status unit tidak bisa diubah dari {$from->label()} ke {$to->label()}.");
    }
}

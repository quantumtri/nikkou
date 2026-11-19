<?php

namespace App\Exceptions;

use DomainException;

/** A goods-receipt rule was broken (message is user-facing, Indonesian). */
class ReceiptRuleViolation extends DomainException {}

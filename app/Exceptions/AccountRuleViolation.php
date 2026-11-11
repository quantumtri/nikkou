<?php

namespace App\Exceptions;

use DomainException;

/** A change to an account that the business rules do not allow (message is user-facing, Indonesian). */
class AccountRuleViolation extends DomainException {}

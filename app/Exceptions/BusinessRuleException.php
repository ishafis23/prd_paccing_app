<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (state/transisi yang tidak valid),
 * berbeda dari AuthorizationException (larangan akses/role).
 */
class BusinessRuleException extends RuntimeException
{
}

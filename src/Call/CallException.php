<?php

declare(strict_types=1);

namespace App\Call;

/**
 * Something the seller did that a call does not allow (an empty line, a call already over…). Its message is a
 * translation key, shown to the seller once translated.
 */
final class CallException extends \DomainException
{
}

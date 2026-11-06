<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * The model answered, but not with what was asked (no JSON, an incomplete scenario…): worth a retry.
 */
final class ModelException extends \RuntimeException
{
}

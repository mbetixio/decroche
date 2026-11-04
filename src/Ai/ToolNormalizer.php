<?php

namespace App\Ai;

use Symfony\AI\Platform\Contract\Normalizer\ToolNormalizer as BaseToolNormalizer;

/**
 * Always sends a parameter schema, even an empty one: some OpenAI-compatible providers refuse a tool without it.
 */
final class ToolNormalizer extends BaseToolNormalizer
{
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $tool = parent::normalize($data, $format, $context);
        $tool['function']['parameters'] ??= ['type' => 'object', 'properties' => new \stdClass()];

        return $tool;
    }
}

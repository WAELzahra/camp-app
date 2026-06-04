<?php

namespace App\Services\AI\Safety;

final class ModerationResult
{
    public function __construct(
        public readonly string $status,
        public readonly array  $reasons,
        public readonly array  $suggestions,
        public readonly float  $confidence,
        public readonly bool   $llm_moderated,
        public readonly string $content_hash,
        public readonly string $moderated_at,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

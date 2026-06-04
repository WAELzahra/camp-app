<?php

namespace App\Services\AI\Safety;

final class RiskFactor
{
    public function __construct(
        public readonly string  $code,
        public readonly string  $label,
        public readonly string  $description,
        public readonly string  $severity,
        public readonly string  $source,
        public readonly ?string $suggestion,
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

<?php

namespace Azuriom\Plugin\GamingHubPanel\Support;

final readonly class CoreCompatibilityResult
{
    public function __construct(
        public bool $compatible,
        public ?string $coreVersion,
        public string $code,
        public string $reason,
    ) {
    }
}

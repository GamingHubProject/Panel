<?php

namespace Azuriom\Plugin\GamingHubPanel\Data;

final readonly class PanelConnection
{
    public function __construct(
        public ?int $providerId,
        public ?int $connectionId,
        public string $panelType,
        public string $baseUrl,
        public string $serverIdentifier,
        public ?string $encryptedApplicationToken,
        public ?string $encryptedClientToken,
        public string $attributionLabel,
        public int $timeout,
        public bool $verifySsl,
        public int $cacheTtl,
        public bool $legacy = false,
    ) {
    }
}

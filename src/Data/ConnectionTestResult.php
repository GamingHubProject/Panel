<?php
namespace Azuriom\Plugin\GamingHubPanel\Data;
final readonly class ConnectionTestResult
{
    public function __construct(
        public bool $success,
        public string $panelType,
        public ?string $version,
        public ?string $serverName,
        public ?string $state,
        public int $latencyMs,
        public ?string $errorCategory = null,
    ) {}
    public function toArray(): array { return get_object_vars($this); }
}

<?php

namespace Azuriom\Plugin\GamingHubPanel\Support;

final class PanelBootDiagnostics
{
    private string $panelVersion = '0.1.010';
    private ?string $coreVersion = null;
    private bool $compatible = false;
    private string $compatibilityCode = 'not_checked';
    private ?string $failureReason = 'Compatibility has not been checked yet.';
    private bool $routesRegistered = false;
    private bool $pelicanRegistered = false;
    private bool $pterodactylRegistered = false;

    /** @var array<string, true> */
    private array $reportedReasons = [];

    public function recordCompatibility(CoreCompatibilityResult $result): void
    {
        $this->coreVersion = $result->coreVersion;
        $this->compatible = $result->compatible;
        $this->compatibilityCode = $result->code;
        $this->failureReason = $result->compatible ? null : $result->reason;
    }

    public function recordRuntimeFailure(string $code, string $reason): void
    {
        $this->compatible = false;
        $this->compatibilityCode = $code;
        $this->failureReason = $reason;
        $this->pelicanRegistered = false;
        $this->pterodactylRegistered = false;
    }

    public function markRoutesRegistered(): void
    {
        $this->routesRegistered = true;
    }

    public function markProviderRegistered(string $providerType): void
    {
        if ($providerType === 'pelican') {
            $this->pelicanRegistered = true;
        }
        if ($providerType === 'pterodactyl') {
            $this->pterodactylRegistered = true;
        }
    }

    public function shouldReport(string $reason): bool
    {
        if (isset($this->reportedReasons[$reason])) {
            return false;
        }

        $this->reportedReasons[$reason] = true;

        return true;
    }

    /** @return array<string, bool|string|null> */
    public function snapshot(): array
    {
        return [
            'panel_version' => $this->panelVersion,
            'core_version' => $this->coreVersion,
            'compatible' => $this->compatible,
            'compatibility_code' => $this->compatibilityCode,
            'routes_registered' => $this->routesRegistered,
            'pelican_provider_type_registered' => $this->pelicanRegistered,
            'pterodactyl_provider_type_registered' => $this->pterodactylRegistered,
            // Backward-compatible diagnostic keys for integrations reading v0.1.1.
            'pelican_registered' => $this->pelicanRegistered,
            'pterodactyl_registered' => $this->pterodactylRegistered,
            'failure_reason' => $this->failureReason,
        ];
    }
}

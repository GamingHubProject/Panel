<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubCore\Models\ProviderInstance;

final class PanelMappingInspector
{
    public function countForConnection(int $connectionId): int
    {
        return $this->providersForConnection($connectionId)->count();
    }

    public function providersForConnection(int $connectionId)
    {
        return ProviderInstance::query()
            ->whereIn('provider_type', ['pelican', 'pterodactyl'])
            ->get()
            ->filter(static fn (ProviderInstance $provider): bool =>
                (int) data_get($provider->configuration, 'panel_connection_id', 0) === $connectionId
            );
    }
}

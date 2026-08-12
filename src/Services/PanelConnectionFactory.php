<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Data\PanelConnection;
use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;

final class PanelConnectionFactory
{
    public function administrative(PanelConnectionProfile $profile): PanelConnection
    {
        return new PanelConnection(
            providerId: null,
            connectionId: (int) $profile->id,
            panelType: (string) $profile->panel_type,
            baseUrl: (string) $profile->base_url,
            serverIdentifier: '',
            encryptedApplicationToken: $profile->encrypted_application_token,
            encryptedClientToken: $profile->encrypted_default_client_token,
            attributionLabel: (string) $profile->name,
            timeout: (int) $profile->timeout,
            verifySsl: (bool) $profile->verify_tls,
            cacheTtl: (int) $profile->cache_ttl,
        );
    }
}

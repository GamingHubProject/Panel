<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubCore\Data\ProviderInstanceData;
use Azuriom\Plugin\GamingHubPanel\Data\PanelConnection;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;

final class ProviderConfiguration
{
    public function __construct(
        private PanelCredentialStore $credentials,
        private PanelSettings $settings,
    ) {
    }

    public function fromProvider(ProviderInstanceData $provider): PanelConnection
    {
        $configuration = $provider->configuration;
        if (array_key_exists('panel_connection_id', $configuration)) {
            return $this->mapped($provider, $configuration);
        }

        return $this->legacy($provider, $configuration);
    }

    /** @param array<string, mixed> $configuration */
    private function mapped(ProviderInstanceData $provider, array $configuration): PanelConnection
    {
        $connectionId = filter_var($configuration['panel_connection_id'] ?? null, FILTER_VALIDATE_INT);
        $identifier = trim((string) ($configuration['panel_server_identifier'] ?? ''));
        if ($connectionId === false || $connectionId < 1 || $identifier === '') {
            throw new PanelApiException('configuration_invalid', 'Panel provider mapping is incomplete.');
        }

        $profile = PanelConnectionProfile::query()->find($connectionId);
        if ($profile === null || ! $profile->enabled) {
            throw new PanelApiException('configuration_invalid', 'The mapped Panel Connection is unavailable or disabled.');
        }
        if (! hash_equals((string) $profile->panel_type, $provider->providerType)) {
            throw new PanelApiException('configuration_invalid', 'The mapped Panel Connection type does not match the provider type.');
        }

        $this->validateIdentifier($provider->providerType, $identifier);
        $override = $this->credentials->clientOverrideCiphertext($provider->id);
        $clientToken = filled($override) ? $override : $profile->encrypted_default_client_token;
        if (! filled($clientToken)) {
            throw new PanelApiException('configuration_invalid', 'No Client API token is available for this mapped server.');
        }

        $global = $this->settings->all();
        $timeout = $this->boundedOverride(
            $configuration['request_timeout'] ?? null,
            (int) ($profile->timeout ?: $global['default_timeout']),
            2,
            30,
        );
        $cacheTtl = $this->boundedOverride(
            $configuration['cache_ttl'] ?? null,
            (int) ($profile->cache_ttl ?: $global['default_ttl']),
            5,
            300,
        );

        $label = $this->label($configuration, (string) $profile->name);

        return new PanelConnection(
            providerId: $provider->id,
            connectionId: (int) $profile->id,
            panelType: $provider->providerType,
            baseUrl: (string) $profile->base_url,
            serverIdentifier: $identifier,
            encryptedApplicationToken: $profile->encrypted_application_token,
            encryptedClientToken: $clientToken,
            attributionLabel: $label,
            timeout: $timeout,
            verifySsl: (bool) $profile->verify_tls,
            cacheTtl: $cacheTtl,
        );
    }

    /** @param array<string, mixed> $configuration */
    private function legacy(ProviderInstanceData $provider, array $configuration): PanelConnection
    {
        foreach (['panel_url', 'api_mode', 'panel_server_identifier'] as $key) {
            if (! array_key_exists($key, $configuration)) {
                throw new PanelApiException('configuration_invalid', 'Legacy direct provider configuration is incomplete.');
            }
        }

        $mode = (string) $configuration['api_mode'];
        if (! in_array($mode, ['client', 'application'], true)) {
            throw new PanelApiException('configuration_invalid', 'Legacy provider API mode is invalid.');
        }

        $identifier = trim((string) $configuration['panel_server_identifier']);
        $this->validateIdentifier($provider->providerType, $identifier);
        $tokens = $this->credentials->ciphertexts($provider->id);
        $clientToken = $mode === 'application' ? $tokens['runtime'] : $tokens['api'];
        $applicationToken = $mode === 'application' ? $tokens['api'] : null;
        if (! filled($clientToken)) {
            throw new PanelApiException('configuration_invalid', 'Legacy provider has no usable Client API token.');
        }

        $global = $this->settings->all();
        $timeout = $this->boundedOverride($configuration['request_timeout'] ?? null, $global['default_timeout'], 2, 30);
        $cacheTtl = $this->boundedOverride($configuration['cache_ttl'] ?? null, $global['default_ttl'], 5, 300);
        $verify = $configuration['ssl_verify'] ?? $global['default_tls_verify'];
        if (! is_bool($verify) && ! in_array($verify, [0, 1, '0', '1'], true)) {
            throw new PanelApiException('configuration_invalid', 'Legacy TLS verification setting is invalid.');
        }

        return new PanelConnection(
            providerId: $provider->id,
            connectionId: null,
            panelType: $provider->providerType,
            baseUrl: (string) $configuration['panel_url'],
            serverIdentifier: $identifier,
            encryptedApplicationToken: $applicationToken,
            encryptedClientToken: $clientToken,
            attributionLabel: $this->label($configuration, ucfirst($provider->providerType)),
            timeout: $timeout,
            verifySsl: (bool) $verify,
            cacheTtl: $cacheTtl,
            legacy: true,
        );
    }

    private function validateIdentifier(string $type, string $identifier): void
    {
        if ($type === 'pelican'
            && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $identifier)) {
            throw new PanelApiException('configuration_invalid', 'Pelican requires a full server UUID.');
        }
        if ($type === 'pterodactyl' && ! preg_match('/^[A-Za-z0-9-]{8,64}$/D', $identifier)) {
            throw new PanelApiException('configuration_invalid', 'Pterodactyl server identifier is invalid.');
        }
    }

    /** @param array<string, mixed> $configuration */
    private function label(array $configuration, string $fallback): string
    {
        $label = trim((string) ($configuration['public_attribution_label'] ?? ''));
        if ($label === '') {
            $label = $fallback;
        }
        if (strlen($label) > 100 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
            throw new PanelApiException('configuration_invalid', 'Public attribution label is invalid.');
        }

        return $label;
    }

    private function boundedOverride(mixed $value, int $fallback, int $minimum, int $maximum): int
    {
        if ($value === null || $value === '') {
            return max($minimum, min($maximum, $fallback));
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < $minimum || $integer > $maximum) {
            throw new PanelApiException('configuration_invalid', 'Provider override is outside the supported bounds.');
        }

        return $integer;
    }
}

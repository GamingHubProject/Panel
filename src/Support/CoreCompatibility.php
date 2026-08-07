<?php

namespace Azuriom\Plugin\GamingHubPanel\Support;

use Composer\Semver\Semver;

class CoreCompatibility
{
    public const CORE_PLUGIN_ID = 'gaming-hub-core';
    public const VERSION_CONSTRAINT = '>=0.6.0 <0.8.0';
    public const MIN_VERSION = '0.6.0';
    public const NEXT_INCOMPATIBLE_VERSION = '0.8.0';

    /** @var array<string, string> */
    private const REQUIRED_INTERFACES = [
        \Azuriom\Plugin\GamingHubCore\Contracts\CapabilityReader::class => 'CapabilityReader',
        \Azuriom\Plugin\GamingHubCore\Contracts\CapabilityReaderRegistry::class => 'CapabilityReaderRegistry',
        \Azuriom\Plugin\GamingHubCore\Contracts\ProviderInstances::class => 'ProviderInstances',
        \Azuriom\Plugin\GamingHubCore\Contracts\ProviderTypeRegistry::class => 'ProviderTypeRegistry',
        \Azuriom\Plugin\GamingHubCore\Contracts\PublicDataPolicyResolver::class => 'PublicDataPolicyResolver',
        \Azuriom\Plugin\GamingHubCore\Contracts\SharedDataGateway::class => 'SharedDataGateway',
    ];

    /** @var array<string, string> */
    private const REQUIRED_CLASSES = [
        \Azuriom\Plugin\GamingHubCore\Data\MetricsData::class => 'MetricsData',
        \Azuriom\Plugin\GamingHubCore\Data\ProviderConfigurationField::class => 'ProviderConfigurationField',
        \Azuriom\Plugin\GamingHubCore\Data\ProviderInstanceData::class => 'ProviderInstanceData',
        \Azuriom\Plugin\GamingHubCore\Data\ProviderType::class => 'ProviderType',
        \Azuriom\Plugin\GamingHubCore\Data\ServerStatusData::class => 'ServerStatusData',
        \Azuriom\Plugin\GamingHubCore\Data\SharedDataResult::class => 'SharedDataResult',
        \Azuriom\Plugin\GamingHubCore\Models\Game::class => 'Game model',
        \Azuriom\Plugin\GamingHubCore\Models\ProviderInstance::class => 'ProviderInstance model',
        \Azuriom\Plugin\GamingHubCore\Models\Server::class => 'Server model',
        \Azuriom\Plugin\GamingHubCore\Validation\ProviderConfigurationValidator::class => 'ProviderConfigurationValidator',
    ];

    public function inspect(): CoreCompatibilityResult
    {
        $path = $this->pluginJsonPath();
        if ($path === null || ! is_file($path)) {
            return $this->failure('core_missing', null, 'Gaming Hub Core plugin metadata was not found.');
        }

        try {
            $metadata = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->failure('core_metadata_invalid', null, 'Gaming Hub Core plugin metadata is invalid JSON.');
        }

        if (! is_array($metadata) || ($metadata['id'] ?? null) !== self::CORE_PLUGIN_ID) {
            return $this->failure('core_metadata_invalid', null, 'Gaming Hub Core plugin metadata has an unexpected identifier.');
        }

        $version = trim((string) ($metadata['version'] ?? ''));
        if ($version === '') {
            return $this->failure('core_version_missing', null, 'Gaming Hub Core does not report a version.');
        }

        if (! $this->versionIsSupported($version)) {
            return $this->failure(
                'core_version_incompatible',
                $version,
                sprintf('Gaming Hub Core %s is incompatible; Gaming Hub Panel requires %s.', $version, self::VERSION_CONSTRAINT),
            );
        }

        foreach (self::REQUIRED_INTERFACES as $symbol => $label) {
            if (! $this->interfaceAvailable($symbol)) {
                return $this->failure(
                    'core_contract_missing',
                    $version,
                    sprintf('Gaming Hub Core %s is missing the required %s contract.', $version, $label),
                );
            }
        }

        foreach (self::REQUIRED_CLASSES as $symbol => $label) {
            if (! $this->classAvailable($symbol)) {
                return $this->failure(
                    'core_contract_missing',
                    $version,
                    sprintf('Gaming Hub Core %s is missing the required %s type.', $version, $label),
                );
            }
        }

        return new CoreCompatibilityResult(
            compatible: true,
            coreVersion: $version,
            code: 'compatible',
            reason: sprintf('Gaming Hub Core %s is compatible.', $version),
        );
    }

    public function available(): bool
    {
        return $this->inspect()->compatible;
    }

    public function version(): ?string
    {
        return $this->inspect()->coreVersion;
    }

    public function failureReason(): ?string
    {
        $result = $this->inspect();

        return $result->compatible ? null : $result->reason;
    }

    protected function pluginJsonPath(): ?string
    {
        return function_exists('plugin_path')
            ? plugin_path(self::CORE_PLUGIN_ID.'/plugin.json')
            : null;
    }

    protected function interfaceAvailable(string $symbol): bool
    {
        return interface_exists($symbol);
    }

    protected function classAvailable(string $symbol): bool
    {
        return class_exists($symbol);
    }

    protected function versionIsSupported(string $version): bool
    {
        if (class_exists(Semver::class)) {
            try {
                return Semver::satisfies($version, self::VERSION_CONSTRAINT);
            } catch (\UnexpectedValueException) {
                return false;
            }
        }

        return version_compare($version, self::MIN_VERSION, '>=')
            && version_compare($version, self::NEXT_INCOMPATIBLE_VERSION, '<');
    }

    private function failure(string $code, ?string $version, string $reason): CoreCompatibilityResult
    {
        return new CoreCompatibilityResult(false, $version, $code, $reason);
    }
}

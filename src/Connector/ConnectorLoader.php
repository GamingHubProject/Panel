<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector;

use Azuriom\Plugin\GamingHubCore\Contracts\{CapabilityReaderRegistry, ProviderTypeRegistry};
use Azuriom\Plugin\GamingHubPanel\Connector\Contracts\{ConnectorInterface, ConnectorRegistry};
use Azuriom\Plugin\GamingHubPanel\Connector\Exceptions\ConnectorRegistrationFailed;

/**
 * Generalizes what GamingHubPanelServiceProvider::registerTypesAndReaders()
 * used to do by hand for exactly two hardcoded provider types: same target
 * registries (Core's real ProviderTypeRegistry/CapabilityReaderRegistry),
 * same preflight-before-mutate ownership discipline, same idempotency
 * guarantee — generalized over an arbitrary set of ConnectorInterface
 * objects. No parallel capability system: every load() ends with a real
 * Core ProviderType registered through Core's own registry.
 */
final class ConnectorLoader
{
    public function __construct(
        private readonly ConnectorRegistry $connectors,
        private readonly ProviderTypeRegistry $types,
        private readonly CapabilityReaderRegistry $readers,
    ) {
    }

    /** @throws ConnectorRegistrationFailed on any conflict or incomplete registration */
    public function load(ConnectorInterface $connector): void
    {
        $providerType = $connector->providerType();

        if ($connector->id() !== $providerType->id) {
            throw ConnectorRegistrationFailed::idMismatch($connector->id(), $providerType->id);
        }

        // Preflight before mutating either registry. A provider type owned
        // by another plugin, or missing a capability this Connector wants
        // to declare a reader for, is a hard conflict rather than a
        // partial registration.
        $existing = $this->types->find($providerType->id);
        if ($existing !== null
            && ($existing->pluginId !== $providerType->pluginId
                || ! $this->supportsAll($existing, $providerType->capabilities))) {
            throw ConnectorRegistrationFailed::ownershipConflict($providerType->id);
        }

        $readerMap = $connector->readers();
        foreach (array_keys($readerMap) as $capability) {
            if (! in_array($capability, $providerType->capabilities, true)) {
                throw ConnectorRegistrationFailed::undeclaredCapability($connector->id(), $capability);
            }
        }

        if ($existing === null) {
            $this->types->register($providerType);
        }

        foreach ($readerMap as $capability => $readerClass) {
            if (! $this->readers->has($providerType->id, $capability)) {
                $this->readers->register($providerType->id, $capability, $readerClass);
            }
        }

        $registered = $this->types->find($providerType->id);
        if ($registered === null
            || $registered->pluginId !== $providerType->pluginId
            || ! $this->supportsAll($registered, array_keys($readerMap))
            || ! $this->allReadersRegistered($providerType->id, array_keys($readerMap))) {
            throw ConnectorRegistrationFailed::incomplete($providerType->id);
        }
    }

    /** Loads every Connector currently known to the ConnectorRegistry. */
    public function loadAll(): void
    {
        foreach ($this->connectors->all() as $connector) {
            $this->load($connector);
        }
    }

    /** @param array<int, string> $capabilities */
    private function supportsAll(object $providerType, array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (! $providerType->supports($capability)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, string> $capabilities */
    private function allReadersRegistered(string $providerTypeId, array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (! $this->readers->has($providerTypeId, $capability)) {
                return false;
            }
        }

        return true;
    }
}

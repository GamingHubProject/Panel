<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector\Contracts;

use Azuriom\Plugin\GamingHubCore\Contracts\CapabilityReader;
use Azuriom\Plugin\GamingHubCore\Data\ProviderType;

/**
 * A Connector is a self-contained description of one provider type plus its
 * capability readers. It performs NO registration itself — it only
 * *describes* what should be registered. ConnectorLoader does the
 * registering, against Core's real ProviderTypeRegistry/
 * CapabilityReaderRegistry, the way GamingHubPanelServiceProvider used to
 * do by hand for exactly two hardcoded types (see docs/CONNECTOR_SDK.md).
 */
interface ConnectorInterface
{
    /**
     * Stable, kebab-case identifier. Must match the id embedded in
     * providerType()->id — ConnectorLoader enforces this before touching
     * either Core registry, rather than trusting either side silently.
     */
    public function id(): string;

    /**
     * The Core ProviderType this Connector wants registered. Must be a
     * detached instance (see ProviderType::detached()) — Connectors never
     * construct an "attached" instance themselves.
     */
    public function providerType(): ProviderType;

    /**
     * @return array<string, class-string<CapabilityReader>> map of
     *   capability name => reader FQCN. Every key MUST already be declared
     *   in providerType()->capabilities — ConnectorLoader validates this
     *   before touching either registry.
     */
    public function readers(): array;
}

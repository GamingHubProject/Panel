<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector\Contracts;

use Azuriom\Plugin\GamingHubPanel\Connector\Exceptions\{DuplicateConnector, UnknownConnector};

/**
 * Panel-local bookkeeping of which Connectors are known — separate from
 * Core's ProviderTypeRegistry, which tracks the ProviderType Core actually
 * ends up serving. Core has no concept of "Connector" at all; this registry
 * never crosses into Core.
 */
interface ConnectorRegistry
{
    /** @throws DuplicateConnector if a Connector with the same id() is already registered */
    public function register(ConnectorInterface $connector): void;

    public function has(string $id): bool;

    /** @throws UnknownConnector if no Connector with this id is registered */
    public function get(string $id): ConnectorInterface;

    /** @return array<string, ConnectorInterface> keyed by id() */
    public function all(): array;
}

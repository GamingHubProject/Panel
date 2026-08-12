<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector;

use Azuriom\Plugin\GamingHubPanel\Connector\Contracts\{ConnectorInterface, ConnectorRegistry};
use Azuriom\Plugin\GamingHubPanel\Connector\Exceptions\{DuplicateConnector, UnknownConnector};

final class InMemoryConnectorRegistry implements ConnectorRegistry
{
    /** @var array<string, ConnectorInterface> */
    private array $connectors = [];

    public function register(ConnectorInterface $connector): void
    {
        if ($this->has($connector->id())) {
            throw DuplicateConnector::withId($connector->id());
        }

        $this->connectors[$connector->id()] = $connector;
    }

    public function has(string $id): bool
    {
        return isset($this->connectors[$id]);
    }

    public function get(string $id): ConnectorInterface
    {
        return $this->connectors[$id] ?? throw UnknownConnector::withId($id);
    }

    /** @return array<string, ConnectorInterface> */
    public function all(): array
    {
        return $this->connectors;
    }
}

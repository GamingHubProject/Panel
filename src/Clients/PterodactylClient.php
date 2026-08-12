<?php

namespace Azuriom\Plugin\GamingHubPanel\Clients;

use Azuriom\Plugin\GamingHubPanel\Data\{ConnectionTestResult, DiscoveryPage, PanelConnection, PanelSnapshot};
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Http\SafePanelHttpClient;
use Azuriom\Plugin\GamingHubPanel\Normalization\PterodactylResponseNormalizer;

final class PterodactylClient extends AbstractPanelClient
{
    private const IDENTIFIER_PATTERN = '/^[A-Za-z0-9-]{8,64}$/D';
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

    public function __construct(
        SafePanelHttpClient $http,
        private PterodactylResponseNormalizer $normalizer,
    ) {
        parent::__construct($http);
    }

    public function snapshot(PanelConnection $connection): PanelSnapshot
    {
        if (! preg_match(self::IDENTIFIER_PATTERN, $connection->serverIdentifier)) {
            throw new PanelApiException('configuration_invalid', 'Pterodactyl server identifier is malformed.');
        }

        $encodedIdentifier = rawurlencode($connection->serverIdentifier);
        $server = $this->http->get($connection, 'api/client/servers/'.$encodedIdentifier, credential: 'client');
        $resources = $this->http->get($connection, 'api/client/servers/'.$encodedIdentifier.'/resources', credential: 'client');

        $snapshot = $this->normalizer->snapshot(
            $this->payload($server),
            $this->payload($resources),
            $this->version($server, ['X-Pterodactyl-Version', 'Pterodactyl-Version']),
        );

        if ($snapshot->resolvedIdentifier === null
            || ! hash_equals($connection->serverIdentifier, $snapshot->resolvedIdentifier)) {
            throw new PanelApiException('invalid_response', 'Pterodactyl response server identifier did not match the configured server.');
        }

        return $snapshot;
    }

    public function discover(PanelConnection $connection, int $page, int $perPage, ?string $search = null): DiscoveryPage
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $query = ['page' => $page, 'per_page' => $perPage, 'include' => 'node,allocations'];
        $search = trim((string) $search);
        if ($search !== '') {
            $query['filter']['name'] = $search;
        }

        $payload = $this->payload($this->http->get($connection, 'api/application/servers', $query, 'application'));
        $items = $payload['data'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new PanelApiException('invalid_response', 'Pterodactyl discovery response is invalid.');
        }

        $servers = [];
        foreach ($items as $item) {
            $attributes = is_array($item) ? ($item['attributes'] ?? null) : null;
            if (! is_array($attributes)) {
                throw new PanelApiException('invalid_response', 'Pterodactyl discovery item is invalid.');
            }

            $identifier = $attributes['identifier'] ?? null;
            $uuid = $attributes['uuid'] ?? null;
            $name = $attributes['name'] ?? null;
            $suspended = $attributes['suspended'] ?? null;

            if (! is_string($identifier)
                || ! preg_match(self::IDENTIFIER_PATTERN, $identifier)
                || ! is_string($name)
                || trim($name) === ''
                || strlen($name) > 255
                || ($uuid !== null && (! is_string($uuid) || ! preg_match(self::UUID_PATTERN, $uuid)))
                || ($suspended !== null && ! is_bool($suspended))) {
                throw new PanelApiException('invalid_response', 'Pterodactyl discovery item has an unsupported shape.');
            }

            $status = $attributes['status'] ?? null;
            $servers[] = [
                'stable_identifier' => $identifier,
                'name' => trim($name),
                'uuid' => is_string($uuid) ? strtolower($uuid) : null,
                'short_identifier' => $identifier,
                'node_name' => $this->nodeName($item, $attributes),
                'suspended' => is_bool($suspended) ? $suspended : null,
                'primary_allocation' => $this->primaryAllocation($item),
                'metadata' => [
                    'status' => is_string($status) && strlen($status) <= 100 ? $status : null,
                ],
            ];
        }

        return new DiscoveryPage($servers, $page, $this->hasMore($payload, $page, $perPage, count($servers)));
    }

    public function test(PanelConnection $connection): ConnectionTestResult
    {
        return $this->safeRuntimeTest(fn (): PanelSnapshot => $this->snapshot($connection), 'pterodactyl');
    }

    public function testApplication(PanelConnection $connection): ConnectionTestResult
    {
        return $this->safeApplicationTest(fn () => $this->discover($connection, 1, 1), 'pterodactyl');
    }

    /** @param array<string, mixed> $payload */
    private function hasMore(array $payload, int $page, int $perPage, int $returned): bool
    {
        $pagination = $payload['meta']['pagination'] ?? null;
        if ($pagination === null) {
            return $returned === $perPage;
        }
        if (! is_array($pagination)
            || ! is_int($pagination['current_page'] ?? null)
            || ! is_int($pagination['total_pages'] ?? null)
            || $pagination['current_page'] < 1
            || $pagination['total_pages'] < $pagination['current_page']) {
            throw new PanelApiException('invalid_response', 'Pterodactyl pagination metadata is invalid.');
        }

        return $pagination['current_page'] < $pagination['total_pages'];
    }
}

<?php

namespace Azuriom\Plugin\GamingHubPanel\Clients;

use Azuriom\Plugin\GamingHubPanel\Data\{ConnectionTestResult, DiscoveryPage, PanelConnection, PanelSnapshot};
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Http\SafePanelHttpClient;
use Azuriom\Plugin\GamingHubPanel\Normalization\PelicanResponseNormalizer;

final class PelicanClient extends AbstractPanelClient
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD';

    public function __construct(
        SafePanelHttpClient $http,
        private PelicanResponseNormalizer $normalizer,
    ) {
        parent::__construct($http);
    }

    public function snapshot(PanelConnection $connection): PanelSnapshot
    {
        if (! preg_match(self::UUID_PATTERN, $connection->serverIdentifier)) {
            throw new PanelApiException('configuration_invalid', 'Pelican requires the full server UUID.');
        }

        $encodedIdentifier = rawurlencode($connection->serverIdentifier);
        $server = $this->http->get($connection, 'api/client/servers/'.$encodedIdentifier, credential: 'client');
        $resources = $this->http->get($connection, 'api/client/servers/'.$encodedIdentifier.'/resources', credential: 'client');

        $snapshot = $this->normalizer->snapshot(
            $this->payload($server),
            $this->payload($resources),
            $this->version($server, ['X-Pelican-Version', 'Pelican-Version']),
        );

        if ($snapshot->resolvedIdentifier === null
            || ! hash_equals(strtolower($connection->serverIdentifier), strtolower($snapshot->resolvedIdentifier))) {
            throw new PanelApiException('invalid_response', 'Pelican response server UUID did not match the configured server.');
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
            throw new PanelApiException('invalid_response', 'Pelican discovery response is invalid.');
        }

        $servers = [];
        foreach ($items as $item) {
            $attributes = is_array($item) ? ($item['attributes'] ?? null) : null;
            if (! is_array($attributes)) {
                throw new PanelApiException('invalid_response', 'Pelican discovery item is invalid.');
            }

            $uuid = $attributes['uuid'] ?? null;
            $shortIdentifier = $attributes['identifier'] ?? null;
            $name = $attributes['name'] ?? null;
            $suspended = $attributes['suspended'] ?? null;

            if (! is_string($uuid)
                || ! preg_match(self::UUID_PATTERN, $uuid)
                || ! is_string($name)
                || trim($name) === ''
                || strlen($name) > 255
                || ($shortIdentifier !== null && (! is_string($shortIdentifier) || ! preg_match('/^[A-Za-z0-9-]{1,64}$/D', $shortIdentifier)))
                || ($suspended !== null && ! is_bool($suspended))) {
                throw new PanelApiException('invalid_response', 'Pelican discovery item has an unsupported shape.');
            }

            $status = $attributes['status'] ?? null;
            $servers[] = [
                'stable_identifier' => strtolower($uuid),
                'name' => trim($name),
                'uuid' => strtolower($uuid),
                'short_identifier' => is_string($shortIdentifier) ? $shortIdentifier : null,
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
        return $this->safeRuntimeTest(fn (): PanelSnapshot => $this->snapshot($connection), 'pelican');
    }

    public function testApplication(PanelConnection $connection): ConnectionTestResult
    {
        return $this->safeApplicationTest(fn () => $this->discover($connection, 1, 1), 'pelican');
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
            throw new PanelApiException('invalid_response', 'Pelican pagination metadata is invalid.');
        }

        return $pagination['current_page'] < $pagination['total_pages'];
    }
}

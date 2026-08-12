<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Models\{DiscoveredPanelServer, PanelConnectionProfile};
use Illuminate\Support\Facades\DB;

final class PanelDiscoveryService
{
    public function __construct(
        private PanelClientFactory $clients,
        private PanelConnectionFactory $connections,
    ) {
    }

    /** @return array{discovered:int,updated:int,missing:int} */
    public function refresh(PanelConnectionProfile $profile): array
    {
        if (! $profile->enabled) {
            throw new PanelApiException('configuration_invalid', 'Disabled Panel Connections cannot be discovered.');
        }
        if (! $profile->hasApplicationToken()) {
            throw new PanelApiException('configuration_invalid', 'An Application API key is required for discovery.');
        }

        $connection = $this->connections->administrative($profile);
        $client = $this->clients->for((string) $profile->panel_type);
        $page = 1;
        $seen = [];
        $rows = [];
        $maximumPages = max(1, min(1000, (int) config('gaming-hub-panel.max_discovery_pages', 250)));

        do {
            if ($page > $maximumPages) {
                throw new PanelApiException('invalid_response', 'Panel discovery exceeded the supported page limit.');
            }

            $result = $client->discover($connection, $page, 100);
            foreach ($result->servers as $server) {
                $identifier = (string) ($server['stable_identifier'] ?? '');
                if ($identifier === '' || isset($seen[$identifier])) {
                    if (isset($seen[$identifier])) {
                        throw new PanelApiException('invalid_response', 'Panel discovery returned a duplicate stable identifier.');
                    }
                    throw new PanelApiException('invalid_response', 'Panel discovery returned an empty stable identifier.');
                }

                $seen[$identifier] = true;
                $rows[] = $server;
            }

            ++$page;
        } while ($result->hasMore);

        return DB::transaction(function () use ($profile, $rows, $seen): array {
            $now = now();
            $discovered = 0;
            $updated = 0;

            foreach ($rows as $server) {
                $model = DiscoveredPanelServer::query()->firstOrNew([
                    'connection_id' => (int) $profile->id,
                    'stable_identifier' => $server['stable_identifier'],
                ]);
                $wasExisting = $model->exists;
                $model->fill([
                    'uuid' => $server['uuid'],
                    'short_identifier' => $server['short_identifier'],
                    'name' => $server['name'],
                    'node_name' => $server['node_name'],
                    'suspended' => $server['suspended'],
                    'primary_allocation' => $server['primary_allocation'],
                    'available' => true,
                    'discovered_at' => $now,
                    'missing_since' => null,
                    'metadata' => $server['metadata'],
                ]);
                $model->save();
                $wasExisting ? ++$updated : ++$discovered;
            }

            $missingQuery = DiscoveredPanelServer::query()
                ->where('connection_id', $profile->id)
                ->where('available', true);
            if ($seen !== []) {
                $missingQuery->whereNotIn('stable_identifier', array_keys($seen));
            }
            $missing = $missingQuery->update([
                'available' => false,
                'missing_since' => $now,
                'updated_at' => $now,
            ]);

            return compact('discovered', 'updated', 'missing');
        });
    }
}

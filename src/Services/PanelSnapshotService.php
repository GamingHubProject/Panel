<?php
namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Data\{PanelConnection, PanelSnapshot};
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Illuminate\Contracts\Cache\Repository;

final class PanelSnapshotService
{
    public function __construct(
        private PanelClientFactory $clients,
        private DiagnosticRecorder $diagnostics,
        private Repository $cache,
    ) {}

    public function get(PanelConnection $connection): PanelSnapshot
    {
        if ($connection->providerId === null) {
            throw new PanelApiException('configuration_invalid', 'A runtime provider mapping is required.');
        }

        $key = $this->key($connection->providerId);
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            $snapshot = PanelSnapshot::fromCacheArray($cached);
            if ($snapshot instanceof PanelSnapshot) {
                return $snapshot;
            }

            $this->cache->forget($key);
        }

        $start = microtime(true);

        try {
            $snapshot = $this->clients->for($connection->panelType)->snapshot($connection);
            $this->cache->put(
                $key,
                $snapshot->toCacheArray(),
                max(5, min(300, $connection->cacheTtl)),
            );
            $this->diagnostics->success(
                $connection->providerId,
                $snapshot->detectedVersion,
                $snapshot->state,
                (int) round((microtime(true) - $start) * 1000),
            );

            return $snapshot;
        } catch (PanelApiException $e) {
            $this->diagnostics->failure(
                $connection->providerId,
                $e->category,
                (int) round((microtime(true) - $start) * 1000),
            );
            throw $e;
        } catch (\Throwable) {
            $this->diagnostics->failure(
                $connection->providerId,
                'unknown_error',
                (int) round((microtime(true) - $start) * 1000),
            );
            throw new PanelApiException('unknown_error', 'Unexpected panel read failure.');
        }
    }

    public function invalidate(int $providerId): void
    {
        $this->cache->forget($this->key($providerId));
    }

    public function isCached(int $providerId): bool
    {
        return $this->cache->has($this->key($providerId));
    }

    private function key(int $id): string
    {
        return 'gaming-hub-panel:snapshot:'.$id;
    }
}

<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector;

use Azuriom\Plugin\GamingHubPanel\Connector\Contracts\{ConnectorInterface, ConnectorRegistry};
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges Manager-installed connector packages into Panel's ConnectorRegistry.
 * This is the piece P1 deliberately deferred (see docs/CONNECTOR_SDK.md's
 * "what this is not" — "Connectors can only be registered in-process").
 *
 * Convention (matches Manager's ExtensionPathGuard::connectorsRoot() and
 * ConnectorToggle):
 * - installed connector packages live at base_path('plugins-connectors')/{id};
 * - a connector is loaded only if base_path('plugins-connectors')/{id}/.enabled
 *   exists (the same marker file ConnectorToggle reads/writes);
 * - base_path('plugins-connectors')/{id}/connector.php is required and its
 *   return value must be a ConnectorInterface instance. The connector package
 *   is responsible for making its own classes loadable (e.g. connector.php
 *   itself requiring its src/ files or registering its own
 *   spl_autoload_register) — Panel does not know or assume anything about a
 *   connector's internal namespace/autoload layout.
 *
 * One connector failing to load (missing/broken connector.php, wrong return
 * type, exception during construction) never prevents other connectors — or
 * Panel itself — from booting; each attempt is fault-isolated and logged.
 */
final class ConnectorDiscovery
{
    private const MARKER = '.enabled';
    private const BOOTSTRAP = 'connector.php';

    public function __construct(private readonly ConnectorRegistry $connectors)
    {
    }

    /**
     * Scans the Connector packages root for enabled connectors and registers
     * each into the ConnectorRegistry. Safe to call even if the directory
     * does not exist (nothing has ever installed a connector).
     */
    public function discover(): void
    {
        $root = base_path('plugins-connectors');
        if (! is_dir($root) || is_link($root)) {
            return;
        }

        $entries = scandir($root) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $path = $root.DIRECTORY_SEPARATOR.$entry;
            if (! is_dir($path) || is_link($path)) {
                continue;
            }

            $this->loadOne($entry, $path);
        }
    }

    private function loadOne(string $id, string $path): void
    {
        if (! is_file($path.'/'.self::MARKER)) {
            // Disabled (or never enabled). Not an error — this connector is
            // simply not participating this request. Re-enabling it takes
            // effect on the next request; nothing here needs invalidating.
            return;
        }

        $bootstrap = $path.'/'.self::BOOTSTRAP;
        if (! is_file($bootstrap)) {
            $this->warn($id, 'Connector is enabled but has no connector.php bootstrap file.');

            return;
        }

        try {
            $connector = (static function (string $file): mixed {
                return require $file;
            })($bootstrap);
        } catch (Throwable $exception) {
            $this->warn($id, 'Connector bootstrap file threw while loading: '.$exception->getMessage());

            return;
        }

        if (! $connector instanceof ConnectorInterface) {
            $this->warn($id, 'Connector bootstrap file did not return a ConnectorInterface instance.');

            return;
        }

        try {
            $this->connectors->register($connector);
        } catch (Throwable $exception) {
            $this->warn($id, 'Connector could not be registered: '.$exception->getMessage());
        }
    }

    private function warn(string $id, string $reason): void
    {
        Log::warning('Gaming Hub Panel could not load a connector package.', [
            'connector_id' => $id,
            'reason' => $reason,
        ]);
    }
}

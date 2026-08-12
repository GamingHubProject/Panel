<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubCore\Contracts\PublicDataPolicyResolver;
use Azuriom\Plugin\GamingHubCore\Models\{Game, ProviderInstance, Server};
use Azuriom\Plugin\GamingHubPanel\Models\{PanelConnectionProfile, ProviderDiagnostic};
use Azuriom\Plugin\GamingHubPanel\Services\{ConnectionHealthSummary, PanelCredentialStore, PanelSnapshotService};
use Azuriom\Plugin\GamingHubPanel\Support\PanelBootDiagnostics;
use Illuminate\View\View;

final class DiagnosticsController extends Controller
{
    public function show(
        Game $game,
        Server $server,
        ProviderInstance $provider,
        PanelCredentialStore $credentials,
        PanelSnapshotService $snapshots,
        PublicDataPolicyResolver $policies,
        PanelBootDiagnostics $bootDiagnostics,
        ConnectionHealthSummary $health,
    ): View {
        $this->assertOwnership($game, $server, $provider);
        $configuration = (array) $provider->configuration;
        $legacy = ! array_key_exists('panel_connection_id', $configuration);
        $connection = $legacy
            ? null
            : PanelConnectionProfile::query()->find((int) data_get($configuration, 'panel_connection_id'));
        $host = parse_url(
            $legacy ? (string) data_get($configuration, 'panel_url') : (string) $connection?->base_url,
            PHP_URL_HOST,
        );
        $presence = $credentials->presence((int) $provider->id);
        $visibility = [];

        foreach ([
            'server.state',
            'resources.cpu_percent',
            'resources.memory_used_bytes',
            'resources.memory_limit_bytes',
            'resources.disk_used_bytes',
            'uptime.seconds',
        ] as $key) {
            $policy = $policies->effective($server, $key);
            $visibility[$key] = ['visible' => $policy->visible, 'attribution' => $policy->attribution];
        }

        return view('gaming-hub-panel::admin.diagnostics.show', [
            'game' => $game,
            'server' => $server,
            'provider' => $provider,
            'diagnostic' => ProviderDiagnostic::query()->where('provider_id', $provider->id)->first(),
            'connection' => $connection,
            'legacy' => $legacy,
            'tokenPresence' => [
                'legacy_api' => $presence['api'],
                'client_override' => $presence['runtime'],
                'connection_application' => $connection?->hasApplicationToken() ?? false,
                'connection_default_client' => $connection?->hasDefaultClientToken() ?? false,
            ],
            'cacheState' => $snapshots->isCached((int) $provider->id) ? 'cached' : 'empty',
            'redactedHost' => $this->redact((string) $host),
            'visibility' => $visibility,
            'bootDiagnostics' => array_merge($bootDiagnostics->snapshot(), $health->summary()),
        ]);
    }

    private function redact(string $host): string
    {
        if ($host === '') {
            return 'Unavailable';
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return preg_replace('/\d+$/', 'x', $host) ?? 'redacted';
        }

        $parts = explode('.', $host);

        return count($parts) > 2 ? '*.'.implode('.', array_slice($parts, -2)) : $host;
    }

    private function assertOwnership(Game $game, Server $server, ProviderInstance $provider): void
    {
        abort_unless(
            $server->game_id === $game->id
            && $provider->server_id === $server->id
            && in_array($provider->provider_type, ['pelican', 'pterodactyl'], true),
            404,
        );
    }
}

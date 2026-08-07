<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubCore\Contracts\ProviderInstances;
use Azuriom\Plugin\GamingHubCore\Models\{Game, ProviderInstance, Server};
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Http\Requests\ReplaceTokenRequest;
use Azuriom\Plugin\GamingHubPanel\Services\{
    DiagnosticRecorder,
    PanelClientFactory,
    PanelCredentialStore,
    PanelSnapshotService,
    ProviderConfiguration
};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};

final class ConnectionController extends Controller
{
    public function test(
        Game $game,
        Server $server,
        ProviderInstance $provider,
        ProviderInstances $providers,
        ProviderConfiguration $configuration,
        PanelClientFactory $clients,
        DiagnosticRecorder $diagnostics,
    ): RedirectResponse {
        $this->assertOwnership($game, $server, $provider);
        $startedAt = microtime(true);

        try {
            $providerData = $providers->findForServer((int) $server->id, (int) $provider->id);
            abort_if($providerData === null, 404);
            $connection = $configuration->fromProvider($providerData);
            $result = $clients->for($provider->provider_type)->test($connection);

            if ($result->success) {
                $diagnostics->success((int) $provider->id, $result->version, (string) $result->state, $result->latencyMs);
            } else {
                $diagnostics->failure((int) $provider->id, (string) $result->errorCategory, $result->latencyMs);
            }

            $panel = ucfirst($result->panelType);
            $version = filled($result->version) ? ' '.$result->version : '';
            $message = $result->success
                ? "Runtime connection succeeded: {$panel}{$version} — {$result->serverName} ({$result->state}), {$result->latencyMs} ms."
                : "Runtime connection failed: {$panel} — {$result->errorCategory}, {$result->latencyMs} ms.";

            return back()->with($result->success ? 'success' : 'error', $message);
        } catch (PanelApiException $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $diagnostics->failure((int) $provider->id, $exception->category, $latency);

            return back()->with('error', 'Runtime connection failed: '.$exception->category.'.');
        } catch (\Throwable) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $diagnostics->failure((int) $provider->id, 'unknown_error', $latency);

            return back()->with('error', 'Runtime connection failed: unknown_error.');
        }
    }

    /**
     * Legacy v0.1.x discovery endpoint. New mappings discover from the global
     * Connections page. It remains available only so legacy direct providers
     * are not broken while administrators migrate them.
     */
    public function discover(
        Request $request,
        Game $game,
        Server $server,
        ProviderInstance $provider,
        ProviderInstances $providers,
        ProviderConfiguration $configuration,
        PanelClientFactory $clients,
    ): JsonResponse {
        $this->assertOwnership($game, $server, $provider);
        if (array_key_exists('panel_connection_id', (array) $provider->configuration)) {
            return response()->json(['error' => 'use_global_connection_discovery'], 409);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $providerData = $providers->findForServer((int) $server->id, (int) $provider->id);
            abort_if($providerData === null, 404);
            $connection = $configuration->fromProvider($providerData);
            $page = $clients->for($provider->provider_type)->discover(
                $connection,
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 25),
                $validated['search'] ?? null,
            );

            return response()->json([
                'servers' => array_map(static fn (array $item): array => [
                    'id' => $item['stable_identifier'],
                    'name' => $item['name'],
                ], $page->servers),
                'page' => $page->page,
                'has_more' => $page->hasMore,
            ]);
        } catch (PanelApiException $exception) {
            return response()->json(['error' => $exception->category], 422);
        } catch (\Throwable) {
            return response()->json(['error' => 'unknown_error'], 422);
        }
    }

    public function replaceToken(
        ReplaceTokenRequest $request,
        Game $game,
        Server $server,
        ProviderInstance $provider,
        string $slot,
        PanelCredentialStore $credentials,
        PanelSnapshotService $snapshots,
    ): RedirectResponse {
        $this->assertOwnership($game, $server, $provider);
        abort_unless(in_array($slot, ['api', 'runtime'], true), 404);
        $validated = $request->validated();
        $mapped = array_key_exists('panel_connection_id', (array) $provider->configuration);

        if ($mapped && $slot !== 'runtime') {
            abort(404);
        }

        if ($slot === 'api') {
            $credentials->replace((int) $provider->id, $validated['token'], null, true);
        } else {
            $credentials->replaceClientOverride((int) $provider->id, $validated['token'], true);
        }

        $snapshots->invalidate((int) $provider->id);

        return back()->with('success', $mapped ? 'Client token override replaced.' : ucfirst($slot).' token replaced.');
    }

    public function removeToken(
        Game $game,
        Server $server,
        ProviderInstance $provider,
        string $slot,
        PanelCredentialStore $credentials,
        PanelSnapshotService $snapshots,
    ): RedirectResponse {
        $this->assertOwnership($game, $server, $provider);
        abort_unless(in_array($slot, ['api', 'runtime'], true), 404);
        $mapped = array_key_exists('panel_connection_id', (array) $provider->configuration);

        if ($mapped && $slot !== 'runtime') {
            abort(404);
        }

        $credentials->remove((int) $provider->id, $slot);
        $snapshots->invalidate((int) $provider->id);

        return back()->with('success', $mapped ? 'Client token override removed.' : 'Credential removed.');
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

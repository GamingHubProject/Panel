<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
use Azuriom\Plugin\GamingHubPanel\Services\{PanelClientFactory, PanelConnectionFactory, PanelDiscoveryService};
use Illuminate\Http\{JsonResponse, RedirectResponse};

final class PanelConnectionActionsController extends Controller
{
    public function test(
        PanelConnectionProfile $connection,
        PanelConnectionFactory $connections,
        PanelClientFactory $clients,
    ): RedirectResponse {
        $startedAt = microtime(true);

        try {
            if (! $connection->hasApplicationToken()) {
                throw new PanelApiException('configuration_invalid', 'An Application API key is required.');
            }

            $result = $clients->for((string) $connection->panel_type)
                ->testApplication($connections->administrative($connection));

            $connection->forceFill([
                'last_test_status' => $result->success ? 'success' : 'failed',
                'last_test_code' => $result->success ? 'ok' : $result->errorCategory,
                'last_test_latency_ms' => $result->latencyMs,
                'last_tested_at' => now(),
                'last_successful_test_at' => $result->success ? now() : $connection->last_successful_test_at,
            ])->save();

            $message = $result->success
                ? "Application API test succeeded: {$result->serverName}; {$result->latencyMs} ms."
                : "Application API test failed: {$result->errorCategory}; {$result->latencyMs} ms.";

            return back()->with($result->success ? 'success' : 'error', $message);
        } catch (PanelApiException $exception) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $connection->forceFill([
                'last_test_status' => 'failed',
                'last_test_code' => $exception->category,
                'last_test_latency_ms' => $latency,
                'last_tested_at' => now(),
            ])->save();

            return back()->with('error', 'Application API test failed: '.$exception->category.'.');
        } catch (\Throwable) {
            $latency = (int) round((microtime(true) - $startedAt) * 1000);
            $connection->forceFill([
                'last_test_status' => 'failed',
                'last_test_code' => 'unknown_error',
                'last_test_latency_ms' => $latency,
                'last_tested_at' => now(),
            ])->save();

            return back()->with('error', 'Application API test failed: unknown_error.');
        }
    }

    public function discover(
        PanelConnectionProfile $connection,
        PanelDiscoveryService $discovery,
    ): RedirectResponse {
        try {
            $result = $discovery->refresh($connection);

            return back()->with(
                'success',
                "Discovery complete: {$result['discovered']} new, {$result['updated']} refreshed, {$result['missing']} no longer returned.",
            );
        } catch (PanelApiException $exception) {
            return back()->with('error', 'Server discovery failed: '.$exception->category.'.');
        } catch (\Throwable) {
            return back()->with('error', 'Server discovery failed: unknown_error.');
        }
    }

    public function servers(PanelConnectionProfile $connection): JsonResponse
    {
        if (! $connection->enabled) {
            return response()->json(['error' => 'connection_disabled', 'servers' => []], 422);
        }

        $servers = $connection->discoveredServers()
            ->where('available', true)
            ->orderBy('name')
            ->get()
            ->map(static fn ($server): array => [
                'id' => (int) $server->id,
                'name' => (string) $server->name,
                'stable_identifier' => (string) $server->stable_identifier,
                'uuid' => $server->uuid,
                'short_identifier' => $server->short_identifier,
                'suspended' => $server->suspended,
            ])
            ->values();

        return response()->json([
            'connection_id' => (int) $connection->id,
            'panel_type' => (string) $connection->panel_type,
            'servers' => $servers,
            'empty' => $servers->isEmpty(),
        ]);
    }
}

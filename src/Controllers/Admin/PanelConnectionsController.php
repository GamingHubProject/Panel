<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubPanel\Http\Requests\SavePanelConnectionRequest;
use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
use Azuriom\Plugin\GamingHubPanel\Services\{PanelConnectionCredentialStore, PanelMappingInspector};
use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PanelConnectionsController extends Controller
{
    public function index(PanelMappingInspector $mappings): View
    {
        $connections = PanelConnectionProfile::query()
            ->withCount(['discoveredServers', 'discoveredServers as available_servers_count' => fn ($query) => $query->where('available', true)])
            ->orderBy('name')
            ->get();

        $mappingCounts = $connections->mapWithKeys(
            static fn (PanelConnectionProfile $connection): array => [
                $connection->id => $mappings->countForConnection((int) $connection->id),
            ],
        );

        return view('gaming-hub-panel::admin.connections.index', compact('connections', 'mappingCounts'));
    }

    public function create(PanelSettings $settings): View
    {
        return view('gaming-hub-panel::admin.connections.create', [
            'connection' => null,
            'defaults' => $settings->all(),
            'tokenPresence' => ['application' => false, 'default_client' => false],
        ]);
    }

    public function store(
        SavePanelConnectionRequest $request,
        PanelConnectionCredentialStore $credentials,
    ): RedirectResponse {
        $connection = DB::transaction(function () use ($request, $credentials): PanelConnectionProfile {
            $connection = PanelConnectionProfile::query()->create(array_merge(
                $request->connectionData(),
                [
                    'uuid' => (string) Str::uuid(),
                    'encrypted_application_token' => null,
                    'encrypted_default_client_token' => null,
                ],
            ));

            $credentials->replace(
                $connection,
                $request->input('application_token'),
                $request->input('default_client_token'),
                false,
            );

            return $connection;
        });

        return to_route('gaming-hub-panel.admin.connections.edit', $connection)
            ->with('success', 'Panel Connection created. Test it before discovery or provider mapping.');
    }

    public function edit(
        PanelConnectionProfile $connection,
        PanelConnectionCredentialStore $credentials,
        PanelMappingInspector $mappings,
        PanelSettings $settings,
    ): View {
        $connection->loadCount([
            'discoveredServers',
            'discoveredServers as available_servers_count' => fn ($query) => $query->where('available', true),
        ]);

        $servers = $connection->discoveredServers()
            ->orderByDesc('available')
            ->orderBy('name')
            ->paginate(50);

        return view('gaming-hub-panel::admin.connections.edit', [
            'connection' => $connection,
            'defaults' => $settings->all(),
            'tokenPresence' => $credentials->presence($connection),
            'mappingCount' => $mappings->countForConnection((int) $connection->id),
            'servers' => $servers,
        ]);
    }

    public function update(
        SavePanelConnectionRequest $request,
        PanelConnectionProfile $connection,
        PanelConnectionCredentialStore $credentials,
        PanelMappingInspector $mappings,
    ): RedirectResponse {
        $data = $request->connectionData();
        $typeChanged = ! hash_equals((string) $connection->panel_type, (string) $data['panel_type']);
        if ($typeChanged && $mappings->countForConnection((int) $connection->id) > 0) {
            return back()->with('error', 'Panel type cannot change while provider mappings use this connection. Reassign or remove them first.');
        }

        DB::transaction(function () use ($request, $connection, $credentials, $data, $typeChanged): void {
            $baseUrlChanged = ! hash_equals((string) $connection->base_url, (string) $data['base_url']);
            $applicationTestInputsChanged = $typeChanged
                || $baseUrlChanged
                || (int) $connection->timeout !== (int) $data['timeout']
                || (bool) $connection->verify_tls !== (bool) $data['verify_tls'];

            $connection->fill($data);
            if ($applicationTestInputsChanged) {
                $connection->last_test_status = null;
                $connection->last_test_code = 'not_tested_after_change';
                $connection->last_test_latency_ms = null;
                $connection->last_tested_at = null;
            }
            $connection->save();

            if ($typeChanged) {
                $connection->discoveredServers()->delete();
            } elseif ($baseUrlChanged) {
                $connection->discoveredServers()->where('available', true)->update([
                    'available' => false,
                    'missing_since' => now(),
                    'updated_at' => now(),
                ]);
            }

            $credentials->replace(
                $connection,
                $request->input('application_token'),
                $request->input('default_client_token'),
                true,
            );
        });

        return back()->with('success', 'Panel Connection updated. Blank secret fields preserved their stored values.');
    }

    public function destroy(
        PanelConnectionProfile $connection,
        PanelMappingInspector $mappings,
    ): RedirectResponse {
        $count = $mappings->countForConnection((int) $connection->id);
        if ($count > 0) {
            return back()->with('error', "This connection is used by {$count} provider mapping(s). Reassign or remove them first.");
        }

        $connection->delete();

        return to_route('gaming-hub-panel.admin.connections.index')
            ->with('success', 'Panel Connection deleted.');
    }
}

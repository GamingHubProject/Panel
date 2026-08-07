<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubCore\Contracts\ProviderTypeRegistry;
use Azuriom\Plugin\GamingHubCore\Models\{Game, ProviderInstance, Server};
use Azuriom\Plugin\GamingHubCore\Validation\ProviderConfigurationValidator;
use Azuriom\Plugin\GamingHubPanel\Http\Requests\SavePanelProviderRequest;
use Azuriom\Plugin\GamingHubPanel\Services\{PanelCredentialStore, PanelSnapshotService};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class PanelProviderController extends Controller
{
    public function __construct(
        private ProviderTypeRegistry $types,
        private PanelCredentialStore $credentials,
        private PanelSnapshotService $snapshots,
        private ProviderConfigurationValidator $validator,
    ) {
    }

    public function store(SavePanelProviderRequest $request, Game $game, Server $server): RedirectResponse
    {
        $this->assertServerOwnership($game, $server);
        $data = $request->providerData();
        $type = $this->types->get($data['provider_type']);

        if (! in_array($data['provider_type'], ['pelican', 'pterodactyl'], true)) {
            $data['configuration'] = $this->validator->validate($type, $data['configuration']);
        }

        $data['game_id'] = $game->id;
        DB::transaction(function () use ($server, $data, $request): void {
            $provider = $server->providers()->create($data);
            if (in_array($provider->provider_type, ['pelican', 'pterodactyl'], true)) {
                $this->credentials->replaceClientOverride(
                    (int) $provider->id,
                    $request->input('client_token_override'),
                    false,
                );
            }
        });

        return to_route('gaming-hub-core.admin.games.servers.providers.index', [$game, $server])
            ->with('success', 'Provider mapping created.');
    }

    public function update(
        SavePanelProviderRequest $request,
        Game $game,
        Server $server,
        ProviderInstance $provider,
    ): RedirectResponse {
        $this->assertProviderOwnership($game, $server, $provider);
        $data = $request->providerData();
        abort_unless($data['provider_type'] === $provider->provider_type, 422);

        $type = $this->types->get($provider->provider_type);
        if (! in_array($provider->provider_type, ['pelican', 'pterodactyl'], true)) {
            $data['configuration'] = $this->validator->validate($type, $data['configuration']);
        }

        DB::transaction(function () use ($provider, $data, $request): void {
            $provider->update($data);
            if (in_array($provider->provider_type, ['pelican', 'pterodactyl'], true)) {
                $this->credentials->replaceClientOverride(
                    (int) $provider->id,
                    $request->input('client_token_override'),
                    true,
                );
            }
        });

        $this->snapshots->invalidate((int) $provider->id);

        return to_route('gaming-hub-core.admin.games.servers.providers.index', [$game, $server])
            ->with('success', $request->preservesLegacy()
                ? 'Legacy direct provider preserved and general fields updated.'
                : 'Provider mapping updated.');
    }

    private function assertServerOwnership(Game $game, Server $server): void
    {
        abort_unless($server->game_id === $game->id, 404);
    }

    private function assertProviderOwnership(Game $game, Server $server, ProviderInstance $provider): void
    {
        $this->assertServerOwnership($game, $server);
        abort_unless($provider->server_id === $server->id, 404);
    }
}

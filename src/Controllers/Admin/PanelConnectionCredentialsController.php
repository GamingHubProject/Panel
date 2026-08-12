<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubPanel\Http\Requests\ReplaceConnectionTokenRequest;
use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
use Azuriom\Plugin\GamingHubPanel\Services\PanelConnectionCredentialStore;
use Illuminate\Http\RedirectResponse;

final class PanelConnectionCredentialsController extends Controller
{
    public function replace(
        ReplaceConnectionTokenRequest $request,
        PanelConnectionProfile $connection,
        string $slot,
        PanelConnectionCredentialStore $credentials,
    ): RedirectResponse {
        abort_unless(in_array($slot, ['application', 'default-client'], true), 404);
        $credentials->replaceSlot($connection, $slot, $request->validated('token'));

        return back()->with('success', $slot === 'application'
            ? 'Application API key replaced.'
            : 'Default Client API token replaced.');
    }

    public function remove(
        PanelConnectionProfile $connection,
        string $slot,
        PanelConnectionCredentialStore $credentials,
    ): RedirectResponse {
        abort_unless(in_array($slot, ['application', 'default-client'], true), 404);
        $credentials->remove($connection, $slot);

        return back()->with('success', $slot === 'application'
            ? 'Application API key removed. Discovery and testing are unavailable until it is replaced.'
            : 'Default Client API token removed. Mappings without their own override will return configuration_invalid.');
    }
}

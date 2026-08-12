<?php

namespace Azuriom\Plugin\GamingHubPanel\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\GamingHubPanel\Http\Requests\SaveSettingsRequest;
use Azuriom\Plugin\GamingHubPanel\Services\ConnectionHealthSummary;
use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;
use Azuriom\Plugin\GamingHubPanel\Support\PanelBootDiagnostics;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class SettingsController extends Controller
{
    public function edit(
        PanelSettings $settings,
        PanelBootDiagnostics $bootDiagnostics,
        ConnectionHealthSummary $health,
    ): View {
        return view('gaming-hub-panel::admin.settings', [
            'settings' => $settings->all(),
            'bootDiagnostics' => array_merge($bootDiagnostics->snapshot(), $health->summary()),
        ]);
    }

    public function update(SaveSettingsRequest $request, PanelSettings $settings): RedirectResponse
    {
        $settings->save($request->validated());

        return back()->with('success', 'Gaming Hub Panel settings saved.');
    }
}

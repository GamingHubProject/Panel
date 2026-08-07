<?php

namespace Azuriom\Plugin\GamingHubPanel\Providers;

use Azuriom\Extensions\Plugin\BaseRouteServiceProvider;
use Azuriom\Plugin\GamingHubPanel\Support\{CoreCompatibility, PanelBootDiagnostics};
use Illuminate\Support\Facades\{Log, Route};

final class RouteServiceProvider extends BaseRouteServiceProvider
{
    public function loadRoutes(): void
    {
        $compatibility = $this->app->make(CoreCompatibility::class)->inspect();
        $diagnostics = $this->app->make(PanelBootDiagnostics::class);
        $diagnostics->recordCompatibility($compatibility);

        if (! $compatibility->compatible) {
            if ($diagnostics->shouldReport($compatibility->reason)) {
                Log::warning('Gaming Hub Panel routes were not registered.', [
                    'panel_plugin' => $this->plugin->id,
                    'detected_core_version' => $compatibility->coreVersion,
                    'compatibility_code' => $compatibility->code,
                    'reason' => $compatibility->reason,
                ]);
            }

            return;
        }

        Route::middleware('admin-access')
            ->prefix('admin/'.$this->plugin->id)
            ->name($this->plugin->id.'.admin.')
            ->group(plugin_path($this->plugin->id.'/routes/admin.php'));

        $diagnostics->markRoutesRegistered();
    }
}

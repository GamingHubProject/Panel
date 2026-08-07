<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;

final class ConnectionHealthSummary
{
    /** @return array{configured:int,healthy:int,failed:int} */
    public function summary(): array
    {
        try {
            $configured = PanelConnectionProfile::query()->count();
            $healthy = PanelConnectionProfile::query()->where('last_test_status', 'success')->count();
            $failed = PanelConnectionProfile::query()->where('last_test_status', 'failed')->count();

            return compact('configured', 'healthy', 'failed');
        } catch (\Throwable) {
            // The settings route can be visited before pending plugin migrations
            // have run. Boot diagnostics must remain available in that state.
            return ['configured' => 0, 'healthy' => 0, 'failed' => 0];
        }
    }
}

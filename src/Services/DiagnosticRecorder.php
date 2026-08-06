<?php
namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Models\ProviderDiagnostic;

final class DiagnosticRecorder
{
    public function success(int $providerId, ?string $version, string $state, int $latencyMs): void
    {
        try {
            ProviderDiagnostic::query()->updateOrCreate(
                ['provider_id' => $providerId],
                [
                    'last_attempted_at' => now(),
                    'last_successful_at' => now(),
                    'last_error_category' => null,
                    'detected_version' => $version,
                    'last_state' => $state,
                    'last_latency_ms' => max(0, $latencyMs),
                ],
            );
        } catch (\Throwable) {
            // Diagnostics must never turn a successful read into a failed read.
        }
    }

    public function failure(int $providerId, string $category, int $latencyMs = 0): void
    {
        try {
            ProviderDiagnostic::query()->updateOrCreate(
                ['provider_id' => $providerId],
                [
                    'last_attempted_at' => now(),
                    'last_error_category' => $category,
                    'last_latency_ms' => max(0, $latencyMs),
                ],
            );
        } catch (\Throwable) {
            // Diagnostics are best effort and cannot mask the normalized failure.
        }
    }
}

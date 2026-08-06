<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Models\PanelCredential;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

final class PanelCredentialStore
{
    /** @return array{api:?string,runtime:?string} Ciphertexts only. */
    public function ciphertexts(int $providerId): array
    {
        $row = PanelCredential::query()->where('provider_id', $providerId)->first();

        return [
            'api' => $row?->encrypted_api_token,
            'runtime' => $row?->encrypted_runtime_token,
        ];
    }

    /** @return array{api:bool,runtime:bool} */
    public function presence(int $providerId): array
    {
        $credentials = $this->ciphertexts($providerId);

        return [
            'api' => filled($credentials['api']),
            'runtime' => filled($credentials['runtime']),
        ];
    }

    public function clientOverrideCiphertext(int $providerId): ?string
    {
        return $this->ciphertexts($providerId)['runtime'];
    }

    public function replaceClientOverride(int $providerId, ?string $token, bool $preserveBlank = true): void
    {
        $row = PanelCredential::query()->firstOrNew(['provider_id' => $providerId]);

        if (! $preserveBlank || filled($token)) {
            $row->encrypted_runtime_token = filled($token)
                ? Crypt::encryptString(trim((string) $token))
                : null;
        }

        if ($row->encrypted_api_token === null && $row->encrypted_runtime_token === null) {
            if ($row->exists) {
                $row->delete();
            }

            return;
        }

        $row->save();
    }

    public function removeClientOverride(int $providerId): void
    {
        $this->remove($providerId, 'runtime');
    }

    /**
     * Legacy direct-provider storage. The API slot is the v0.1.x API token and
     * the runtime slot is the optional Client API token used in application mode.
     */
    public function replace(
        int $providerId,
        ?string $apiToken,
        ?string $runtimeToken,
        bool $preserveBlank = true,
    ): void {
        $row = PanelCredential::query()->firstOrNew(['provider_id' => $providerId]);

        if (! $preserveBlank || filled($apiToken)) {
            $row->encrypted_api_token = filled($apiToken)
                ? Crypt::encryptString(trim((string) $apiToken))
                : null;
        }

        if (! $preserveBlank || filled($runtimeToken)) {
            $row->encrypted_runtime_token = filled($runtimeToken)
                ? Crypt::encryptString(trim((string) $runtimeToken))
                : null;
        }

        $row->save();
    }

    public function remove(int $providerId, string $slot): void
    {
        $row = PanelCredential::query()->where('provider_id', $providerId)->first();
        if ($row === null) {
            return;
        }

        if ($slot === 'api') {
            $row->encrypted_api_token = null;
        } elseif ($slot === 'runtime') {
            $row->encrypted_runtime_token = null;
        } else {
            throw new InvalidArgumentException('Unknown credential slot.');
        }

        if ($row->encrypted_api_token === null && $row->encrypted_runtime_token === null) {
            $row->delete();
        } else {
            $row->save();
        }
    }
}

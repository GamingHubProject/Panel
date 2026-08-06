<?php

namespace Azuriom\Plugin\GamingHubPanel\Services;

use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

final class PanelConnectionCredentialStore
{
    /** @return array{application:bool,default_client:bool} */
    public function presence(PanelConnectionProfile $connection): array
    {
        return [
            'application' => $connection->hasApplicationToken(),
            'default_client' => $connection->hasDefaultClientToken(),
        ];
    }

    public function replace(
        PanelConnectionProfile $connection,
        ?string $applicationToken,
        ?string $defaultClientToken,
        bool $preserveBlank = true,
    ): void {
        $applicationChanged = false;

        if (! $preserveBlank || filled($applicationToken)) {
            $next = filled($applicationToken)
                ? Crypt::encryptString(trim((string) $applicationToken))
                : null;
            $applicationChanged = $connection->encrypted_application_token !== $next;
            $connection->encrypted_application_token = $next;
        }

        if (! $preserveBlank || filled($defaultClientToken)) {
            $connection->encrypted_default_client_token = filled($defaultClientToken)
                ? Crypt::encryptString(trim((string) $defaultClientToken))
                : null;
        }

        if ($applicationChanged) {
            $this->invalidateApplicationTest($connection);
        }

        $connection->save();
    }

    public function replaceSlot(PanelConnectionProfile $connection, string $slot, string $token): void
    {
        $ciphertext = Crypt::encryptString(trim($token));

        if ($slot === 'application') {
            $connection->encrypted_application_token = $ciphertext;
            $this->invalidateApplicationTest($connection);
        } elseif ($slot === 'default-client') {
            $connection->encrypted_default_client_token = $ciphertext;
        } else {
            throw new InvalidArgumentException('Unknown connection credential slot.');
        }

        $connection->save();
    }

    public function remove(PanelConnectionProfile $connection, string $slot): void
    {
        if ($slot === 'application') {
            if ($connection->encrypted_application_token === null) {
                return;
            }
            $connection->encrypted_application_token = null;
            $this->invalidateApplicationTest($connection);
        } elseif ($slot === 'default-client') {
            if ($connection->encrypted_default_client_token === null) {
                return;
            }
            $connection->encrypted_default_client_token = null;
        } else {
            throw new InvalidArgumentException('Unknown connection credential slot.');
        }

        $connection->save();
    }

    private function invalidateApplicationTest(PanelConnectionProfile $connection): void
    {
        $connection->last_test_status = null;
        $connection->last_test_code = 'not_tested_after_change';
        $connection->last_test_latency_ms = null;
        $connection->last_tested_at = null;
    }
}

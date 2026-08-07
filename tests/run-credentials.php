<?php

declare(strict_types=1);

namespace Illuminate\Support\Facades {
    final class Crypt
    {
        public static function encryptString(string $value): string
        {
            return 'cipher:'.base64_encode($value);
        }
    }
}

namespace Azuriom\Plugin\GamingHubPanel\Models {
    final class PanelConnectionProfile
    {
        public ?string $encrypted_application_token = null;
        public ?string $encrypted_default_client_token = null;
        public int $saveCount = 0;

        public function hasApplicationToken(): bool
        {
            return filled($this->encrypted_application_token);
        }

        public function hasDefaultClientToken(): bool
        {
            return filled($this->encrypted_default_client_token);
        }

        public function save(): void
        {
            ++$this->saveCount;
        }
    }
}

namespace {
    use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
    use Azuriom\Plugin\GamingHubPanel\Services\PanelConnectionCredentialStore;

    if (! function_exists('filled')) {
        function filled(mixed $value): bool
        {
            return ! ($value === null || $value === '' || $value === []);
        }
    }

    require dirname(__DIR__).'/src/Services/PanelConnectionCredentialStore.php';

    $failures = [];
    $check = static function (bool $ok, string $name) use (&$failures): void {
        echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
        if (! $ok) {
            $failures[] = $name;
        }
    };

    $store = new PanelConnectionCredentialStore();
    $connection = new PanelConnectionProfile();
    $store->replace($connection, 'ptla-secret', 'ptlc-secret', false);
    $check($connection->encrypted_application_token !== 'ptla-secret', 'Application API key is encrypted before storage');
    $check($connection->encrypted_default_client_token !== 'ptlc-secret', 'default Client token is encrypted before storage');
    $check($connection->encrypted_application_token === 'cipher:'.base64_encode('ptla-secret'), 'Application ciphertext is the encrypted value');
    $check($store->presence($connection) === ['application' => true, 'default_client' => true], 'credential presence exposes booleans only');

    $applicationCipher = $connection->encrypted_application_token;
    $clientCipher = $connection->encrypted_default_client_token;
    $store->replace($connection, '', null, true);
    $check($connection->encrypted_application_token === $applicationCipher, 'blank Application field preserves stored value');
    $check($connection->encrypted_default_client_token === $clientCipher, 'blank default Client field preserves stored value');

    $store->replaceSlot($connection, 'application', 'replacement-application');
    $check($connection->encrypted_application_token === 'cipher:'.base64_encode('replacement-application'), 'explicit Application Replace action overwrites ciphertext');
    $store->replaceSlot($connection, 'default-client', 'replacement-client');
    $check($connection->encrypted_default_client_token === 'cipher:'.base64_encode('replacement-client'), 'explicit default Client Replace action overwrites ciphertext');

    $store->remove($connection, 'application');
    $check($connection->encrypted_application_token === null, 'explicit Application Remove action clears ciphertext');
    $store->remove($connection, 'default-client');
    $check($connection->encrypted_default_client_token === null, 'explicit default Client Remove action clears ciphertext');
    $check($store->presence($connection) === ['application' => false, 'default_client' => false], 'removed credentials report absent without rendering secrets');

    exit($failures === [] ? 0 : 1);
}

<?php

declare(strict_types=1);

namespace Azuriom\Plugin\GamingHubCore\Data {
    final class ProviderInstanceData
    {
        /** @param array<string,mixed> $configuration */
        public function __construct(
            public int $id,
            public string $providerType,
            public array $configuration,
        ) {
        }
    }
}

namespace Azuriom\Plugin\GamingHubPanel\Models {
    final class PanelConnectionQuery
    {
        public function find(int $id): ?PanelConnectionProfile
        {
            return PanelConnectionProfile::$records[$id] ?? null;
        }
    }

    final class PanelConnectionProfile
    {
        /** @var array<int,self> */
        public static array $records = [];

        public function __construct(
            public int $id,
            public string $name,
            public string $panel_type,
            public string $base_url,
            public ?string $encrypted_application_token,
            public ?string $encrypted_default_client_token,
            public int $timeout,
            public int $cache_ttl,
            public bool $verify_tls,
            public bool $enabled,
        ) {
        }

        public static function query(): PanelConnectionQuery
        {
            return new PanelConnectionQuery();
        }
    }
}

namespace Azuriom\Plugin\GamingHubPanel\Services {
    final class PanelCredentialStore
    {
        /** @var array<int,?string> */
        public array $overrides = [];
        /** @var array<int,array{api:?string,runtime:?string}> */
        public array $legacy = [];

        public function clientOverrideCiphertext(int $providerId): ?string
        {
            return $this->overrides[$providerId] ?? null;
        }

        /** @return array{api:?string,runtime:?string} */
        public function ciphertexts(int $providerId): array
        {
            return $this->legacy[$providerId] ?? ['api' => null, 'runtime' => null];
        }
    }
}

namespace Azuriom\Plugin\GamingHubPanel\Settings {
    class PanelSettings
    {
        /** @param array<string,mixed> $settings */
        public function __construct(private array $settings) {}
        /** @return array<string,mixed> */
        public function all(): array { return $this->settings; }
    }
}

namespace {
    use Azuriom\Plugin\GamingHubCore\Data\ProviderInstanceData;
    use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
    use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
    use Azuriom\Plugin\GamingHubPanel\Services\{PanelCredentialStore, ProviderConfiguration};
    use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;

    if (! function_exists('filled')) {
        function filled(mixed $value): bool
        {
            return ! ($value === null || $value === '' || $value === []);
        }
    }

    $root = dirname(__DIR__).'/src';
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Azuriom\\Plugin\\GamingHubPanel\\';
        if (! str_starts_with($class, $prefix) || class_exists($class, false)) {
            return;
        }
        $path = $root.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }
    });

    $failures = [];
    $check = static function (bool $ok, string $name) use (&$failures): void {
        echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
        if (! $ok) {
            $failures[] = $name;
        }
    };
    $expectConfigurationInvalid = static function (callable $callback, string $name) use ($check): void {
        try {
            $callback();
            $check(false, $name);
        } catch (PanelApiException $exception) {
            $check($exception->category === 'configuration_invalid', $name);
        }
    };

    $credentials = new PanelCredentialStore();
    $settings = new PanelSettings([
        'default_timeout' => 8,
        'default_ttl' => 15,
        'default_tls_verify' => true,
    ]);
    $configuration = new ProviderConfiguration($credentials, $settings);

    PanelConnectionProfile::$records[10] = new PanelConnectionProfile(
        10,
        'Main Pelican',
        'pelican',
        'https://pelican.example',
        'application-cipher',
        'default-client-cipher',
        9,
        20,
        false,
        true,
    );
    $credentials->overrides[50] = 'override-client-cipher';
    $mapped = $configuration->fromProvider(new ProviderInstanceData(50, 'pelican', [
        'panel_connection_id' => 10,
        'panel_server_identifier' => '123e4567-e89b-42d3-a456-426614174000',
        'public_attribution_label' => 'Safe label',
        'request_timeout' => 12,
        'cache_ttl' => 30,
    ]));
    $check($mapped->connectionId === 10 && $mapped->providerId === 50, 'mapped provider retains connection and provider IDs internally');
    $check($mapped->encryptedClientToken === 'override-client-cipher', 'per-server Client token override has priority');
    $check($mapped->encryptedApplicationToken === 'application-cipher', 'connection Application ciphertext remains administrative/internal');
    $check($mapped->timeout === 12 && $mapped->cacheTtl === 30, 'provider timeout and cache overrides have priority');
    $check($mapped->verifySsl === false, 'TLS policy is inherited from connection');
    $check($mapped->attributionLabel === 'Safe label', 'safe attribution override retained internally');

    unset($credentials->overrides[51]);
    $fallback = $configuration->fromProvider(new ProviderInstanceData(51, 'pelican', [
        'panel_connection_id' => 10,
        'panel_server_identifier' => '123e4567-e89b-42d3-a456-426614174000',
        'request_timeout' => null,
        'cache_ttl' => null,
    ]));
    $check($fallback->encryptedClientToken === 'default-client-cipher', 'connection default Client token is the fallback');
    $check($fallback->timeout === 9 && $fallback->cacheTtl === 20, 'connection timeout and cache defaults are inherited');

    PanelConnectionProfile::$records[11] = new PanelConnectionProfile(
        11,
        'Global fallback',
        'pelican',
        'https://fallback.example',
        'application-cipher',
        'client-cipher',
        0,
        0,
        true,
        true,
    );
    $globalFallback = $configuration->fromProvider(new ProviderInstanceData(52, 'pelican', [
        'panel_connection_id' => 11,
        'panel_server_identifier' => '123e4567-e89b-42d3-a456-426614174000',
    ]));
    $check($globalFallback->timeout === 8 && $globalFallback->cacheTtl === 15, 'extension global defaults are the final timeout/cache fallback');

    PanelConnectionProfile::$records[12] = new PanelConnectionProfile(
        12,
        'No runtime credential',
        'pelican',
        'https://missing.example',
        'application-cipher',
        null,
        8,
        15,
        true,
        true,
    );
    $expectConfigurationInvalid(
        fn () => $configuration->fromProvider(new ProviderInstanceData(53, 'pelican', [
            'panel_connection_id' => 12,
            'panel_server_identifier' => '123e4567-e89b-42d3-a456-426614174000',
        ])),
        'missing Client token resolves to configuration_invalid',
    );

    PanelConnectionProfile::$records[13] = new PanelConnectionProfile(
        13,
        'Disabled',
        'pelican',
        'https://disabled.example',
        'application-cipher',
        'client-cipher',
        8,
        15,
        true,
        false,
    );
    $expectConfigurationInvalid(
        fn () => $configuration->fromProvider(new ProviderInstanceData(54, 'pelican', [
            'panel_connection_id' => 13,
            'panel_server_identifier' => '123e4567-e89b-42d3-a456-426614174000',
        ])),
        'disabled connection fails safely at runtime',
    );
    $expectConfigurationInvalid(
        fn () => $configuration->fromProvider(new ProviderInstanceData(55, 'pterodactyl', [
            'panel_connection_id' => 10,
            'panel_server_identifier' => 'abcd1234',
        ])),
        'connection/provider type mismatch fails safely',
    );

    $credentials->legacy[60] = ['api' => 'legacy-client-cipher', 'runtime' => null];
    $legacyClient = $configuration->fromProvider(new ProviderInstanceData(60, 'pterodactyl', [
        'panel_url' => 'https://legacy.example',
        'api_mode' => 'client',
        'panel_server_identifier' => 'abcd1234',
        'ssl_verify' => true,
    ]));
    $check($legacyClient->legacy && $legacyClient->encryptedClientToken === 'legacy-client-cipher', 'legacy client-mode provider remains usable without recreation');

    $credentials->legacy[61] = ['api' => 'legacy-application-cipher', 'runtime' => 'legacy-runtime-cipher'];
    $legacyApplication = $configuration->fromProvider(new ProviderInstanceData(61, 'pelican', [
        'panel_url' => 'https://legacy-app.example',
        'api_mode' => 'application',
        'panel_server_identifier' => '123e4567-e89b-42d3-a456-426614174000',
        'ssl_verify' => false,
    ]));
    $check($legacyApplication->legacy, 'legacy application-mode provider is explicitly marked legacy');
    $check($legacyApplication->encryptedApplicationToken === 'legacy-application-cipher', 'legacy Application token is preserved');
    $check($legacyApplication->encryptedClientToken === 'legacy-runtime-cipher', 'legacy runtime Client token is preserved');

    exit($failures === [] ? 0 : 1);
}

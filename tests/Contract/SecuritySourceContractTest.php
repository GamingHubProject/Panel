<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class SecuritySourceContractTest extends TestCase
{
    public function testCredentialsAreNotCoreConfigurationFields(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Providers/GamingHubPanelServiceProvider.php');
        self::assertStringNotContainsString("ProviderConfigurationField('api_token'", $source);
        self::assertStringNotContainsString("ProviderConfigurationField('runtime_token'", $source);
        self::assertStringNotContainsString("ProviderConfigurationField('panel_url'", $source);
    }

    public function testModelsHideCiphertext(): void
    {
        $root = dirname(__DIR__, 2);
        $legacy = file_get_contents($root.'/src/Models/PanelCredential.php');
        self::assertStringContainsString("protected \$hidden=['encrypted_api_token','encrypted_runtime_token']", $legacy);

        $connection = file_get_contents($root.'/src/Models/PanelConnectionProfile.php');
        self::assertStringContainsString("'encrypted_application_token'", $connection);
        self::assertStringContainsString("'encrypted_default_client_token'", $connection);
        self::assertStringContainsString('protected $hidden', $connection);
    }

    public function testNoPowerEndpoints(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
        foreach (['start', 'stop', 'restart', 'kill', 'console', 'files', 'rcon'] as $word) {
            self::assertStringNotContainsString('/'.$word, $routes);
        }
    }
}

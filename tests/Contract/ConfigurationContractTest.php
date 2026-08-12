<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class ConfigurationContractTest extends TestCase
{
    public function testProviderOverridesAndConnectionSecretsUseBoundedBlankPreservingInputs(): void
    {
        $root = dirname(__DIR__, 2);
        $request = file_get_contents($root.'/src/Http/Requests/SavePanelProviderRequest.php');
        self::assertStringContainsString("'min:2', 'max:30'", $request);
        self::assertStringContainsString("'min:5', 'max:300'", $request);
        self::assertStringContainsString("protected \$dontFlash = ['client_token_override']", $request);

        $connectionRequest = file_get_contents($root.'/src/Http/Requests/SavePanelConnectionRequest.php');
        self::assertStringContainsString("protected \$dontFlash = ['application_token', 'default_client_token']", $connectionRequest);

        $store = file_get_contents($root.'/src/Services/PanelConnectionCredentialStore.php');
        self::assertStringContainsString('if (! $preserveBlank || filled($applicationToken))', $store);
        self::assertStringContainsString('if (! $preserveBlank || filled($defaultClientToken))', $store);
        self::assertStringContainsString('Crypt::encryptString', $store);
    }

    public function testSecretsAreNotProviderConfigurationFields(): void
    {
        $provider = file_get_contents(dirname(__DIR__, 2).'/src/Providers/GamingHubPanelServiceProvider.php');
        self::assertStringNotContainsString("ProviderConfigurationField('api_token'", $provider);
        self::assertStringNotContainsString("ProviderConfigurationField('runtime_token'", $provider);
        self::assertStringNotContainsString("ProviderConfigurationField('panel_url'", $provider);

        // P0 (Panel Connector foundation) stubbed providerDefinitions() to
        // return [], so no ProviderConfigurationField is declared at all
        // right now, including panel_connection_id/panel_server_identifier.
        // See docs/CONNECTOR_MIGRATION_AUDIT.md.
        self::assertStringNotContainsString('ProviderConfigurationField(', $provider);
    }
}

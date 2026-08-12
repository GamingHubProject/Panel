<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class PanelConnectionContractTest extends TestCase
{
    public function testGlobalConnectionAndDiscoveryTablesAreDeclared(): void
    {
        $root = dirname(__DIR__, 2);
        $connections = file_get_contents($root.'/database/migrations/2026_08_06_000000_create_gaminghub_panel_connections_table.php');
        self::assertStringContainsString('gaminghub_panel_connections', $connections);
        self::assertStringContainsString('encrypted_application_token', $connections);
        self::assertStringContainsString('encrypted_default_client_token', $connections);

        $servers = file_get_contents($root.'/database/migrations/2026_08_06_001000_create_gaminghub_panel_discovered_servers_table.php');
        self::assertStringContainsString('gaminghub_panel_discovered_servers', $servers);
        self::assertStringContainsString("unique(['connection_id', 'stable_identifier']", $servers);
        self::assertStringContainsString('missing_since', $servers);
    }

    public function testProviderFormMapsConnectionAndDiscoveredServer(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/core-overrides/admin/providers/_form.blade.php');
        self::assertStringContainsString('Panel connection', $view);
        self::assertStringContainsString('Panel server', $view);
        self::assertStringContainsString('data-servers-url', $view);
        self::assertStringContainsString('client_token_override', $view);
        self::assertStringNotContainsString('name="configuration[panel_url]"', $view);
        self::assertStringNotContainsString('name="api_token"', $view);
    }

    public function testRuntimeCredentialResolutionOrderIsExplicit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Services/ProviderConfiguration.php');
        self::assertLessThan(
            strpos($source, 'encrypted_default_client_token'),
            strpos($source, 'clientOverrideCiphertext'),
        );
        self::assertStringContainsString("PanelApiException('configuration_invalid'", $source);
        self::assertStringContainsString('private function legacy', $source);
    }
}

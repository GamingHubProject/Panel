<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class RegistrationContractTest extends TestCase
{
    public function testProviderTypeAndReaderRegistrationIsStubbedForP0(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Providers/GamingHubPanelServiceProvider.php');

        // PANEL_TYPES deliberately still names both panels — it protects
        // existing installations' lifecycle-hook cleanup independent of
        // whether new registration is live. This is not a regression.
        foreach (['pelican', 'pterodactyl'] as $provider) {
            self::assertStringContainsString("'{$provider}'", $source);
        }

        // The four concrete reader classes must NOT be referenced anywhere
        // in the service provider — P0 stubs registration to empty arrays,
        // and re-adding these references would silently start P3 outside
        // of the Connector SDK.
        foreach ([
            'PelicanServerStatusReader',
            'PelicanMetricsReader',
            'PterodactylServerStatusReader',
            'PterodactylMetricsReader',
        ] as $reader) {
            self::assertStringNotContainsString($reader, $source);
        }

        self::assertMatchesRegularExpression(
            '/private function providerDefinitions\(\): array\s*\{\s*return \[\];\s*\}/',
            $source,
        );
        self::assertMatchesRegularExpression(
            '/private function readerRegistrations\(\): array\s*\{\s*return \[\];\s*\}/',
            $source,
        );

        self::assertStringContainsString('! $readers->has', $source);
        self::assertStringContainsString('$this->app->booted', $source);
        self::assertStringNotContainsString('View::prependNamespace', $source);
    }

    public function testCoreCompatibilityIsPresenceOnlyAndUsesInterfaceProbe(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Support/CoreCompatibility.php');

        self::assertStringContainsString("VERSION_CONSTRAINT = '*'", $source);
        self::assertStringContainsString('interface_exists($symbol)', $source);
        self::assertStringContainsString('SharedDataGateway::class', $source);
        self::assertStringNotContainsString('class_exists(\\Azuriom\\Plugin\\GamingHubCore\\Contracts\\ProviderTypeRegistry::class)', $source);
    }

    public function testPluginDeclaresExactProvidersAndCoreDependency(): void
    {
        $plugin = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/plugin.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame([
            '\\Azuriom\\Plugin\\GamingHubPanel\\Providers\\GamingHubPanelServiceProvider',
            '\\Azuriom\\Plugin\\GamingHubPanel\\Providers\\RouteServiceProvider',
        ], $plugin['providers']);
        self::assertSame('*', $plugin['dependencies']['gaming-hub-core']);
    }
}

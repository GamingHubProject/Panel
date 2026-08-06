<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class RegistrationContractTest extends TestCase
{
    public function testBothProvidersAndFourReadersAreDeclared(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Providers/GamingHubPanelServiceProvider.php');

        foreach (['pelican', 'pterodactyl'] as $provider) {
            self::assertStringContainsString("'{$provider}'", $source);
        }

        foreach ([
            'PelicanServerStatusReader',
            'PelicanMetricsReader',
            'PterodactylServerStatusReader',
            'PterodactylMetricsReader',
        ] as $reader) {
            self::assertStringContainsString($reader.'::class', $source);
        }

        self::assertStringContainsString('! $readers->has', $source);
        self::assertStringContainsString('$this->app->booted', $source);
    }

    public function testCoreCompatibilityIsBoundedToZeroSixAndUsesInterfaceProbe(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Support/CoreCompatibility.php');

        self::assertStringContainsString("VERSION_CONSTRAINT = '>=0.6.0 <0.7.0'", $source);
        self::assertStringContainsString('interface_exists($symbol)', $source);
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
        self::assertSame('^0.6.0', $plugin['dependencies']['gaming-hub-core']);
    }
}

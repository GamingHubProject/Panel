<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PackagingContractTest extends TestCase
{
    public function testMetadataMatches(): void
    {
        $root = dirname(__DIR__, 2);
        $plugin = json_decode(file_get_contents($root.'/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest = json_decode(file_get_contents($root.'/gaming-hub-extension.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('gaming-hub-panel', $plugin['id']);
        self::assertSame('0.1.010', $plugin['version']);
        self::assertSame($plugin['id'], $manifest['id']);
        self::assertSame($plugin['version'], $manifest['version']);
        self::assertSame('*', $plugin['dependencies']['gaming-hub-core']);
        self::assertArrayNotHasKey('gaming-hub-core', $manifest['requires']);
        self::assertSame('*', $manifest['requires']['extensions']['gaming-hub-core']);
        self::assertSame('https://github.com/GamingHubProject/Panel', $plugin['url']);
        self::assertSame('https://github.com/GamingHubProject/Panel', $manifest['repository']);
        self::assertSame([], $manifest['provides']['providers']);
        self::assertSame(['server-status', 'metrics'], $manifest['provides']['capabilities']);
    }

    public function testNoForbiddenCapabilities(): void
    {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 2).'/gaming-hub-extension.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach (['power', 'console', 'files', 'rcon', 'commands'] as $forbidden) {
            self::assertNotContains($forbidden, $manifest['provides']['capabilities']);
        }
    }
}

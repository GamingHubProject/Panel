<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class EndpointPermissionContractTest extends TestCase
{
    public function testSpecificPermissionsAndOwnershipChecks(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = file_get_contents($root.'/routes/admin.php');
        foreach ([
            'connections.view', 'connections.manage', 'connections.test', 'servers.discover',
            'diagnostics.view', 'providers.configure', 'settings.manage',
        ] as $permission) {
            self::assertStringContainsString('gaminghub-panel.'.$permission, $routes.file_get_contents($root.'/src/Providers/GamingHubPanelServiceProvider.php'));
        }
        foreach (['connections/{connection}/test', 'connections/{connection}/discover', 'credentials/{slot}', 'diagnostics'] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        $controller = file_get_contents($root.'/src/Controllers/Admin/ConnectionController.php');
        self::assertStringContainsString('$provider->server_id === $server->id', $controller);
    }

    public function testNoStateChangingPanelOperations(): void
    {
        $all = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $all .= file_get_contents($file->getPathname());
            }
        }
        foreach (['/power', 'sendCommand', 'reinstall', 'startServer', 'stopServer', 'restartServer'] as $needle) {
            self::assertStringNotContainsString($needle, $all);
        }
    }
}

<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class ConnectorSdkContractTest extends TestCase
{
    public function testConnectorInterfaceDeclaresExactlyTheRegistrationContract(): void
    {
        $source = file_get_contents($this->root().'/src/Connector/Contracts/ConnectorInterface.php');

        self::assertStringContainsString('function id(): string', $source);
        self::assertStringContainsString('function providerType(): ProviderType', $source);
        self::assertStringContainsString('function readers(): array', $source);

        // No config/identifier-validation hook — deliberately out of scope
        // for P1, documented as a known gap. See docs/CONNECTOR_SDK.md.
        self::assertStringNotContainsString('validateIdentifier', $source);
    }

    public function testConnectorLoaderDeclaresLoadAndLoadAll(): void
    {
        $source = file_get_contents($this->root().'/src/Connector/ConnectorLoader.php');

        self::assertStringContainsString('function load(ConnectorInterface $connector): void', $source);
        self::assertStringContainsString('function loadAll(): void', $source);

        // The Loader must target Core's real registries, never a parallel
        // capability system (the One Rule).
        self::assertStringContainsString('ProviderTypeRegistry $types', $source);
        self::assertStringContainsString('CapabilityReaderRegistry $readers', $source);
    }

    public function testConnectorSdkNeverReferencesPelicanOrPterodactyl(): void
    {
        // Structural tripwire: nothing under src/Connector/ (or this test
        // file itself) may name a concrete integration. P1 designs and
        // proves the SDK contract only — a real Pelican extraction is P3,
        // gated on P2, and must not happen by accident inside the SDK.
        $connectorDir = $this->root().'/src/Connector';
        $forbidden = ['pelican', 'Pelican', 'pterodactyl', 'Pterodactyl'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($connectorDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            foreach ($forbidden as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $contents,
                    sprintf('%s must not reference "%s"', $file->getPathname(), $needle),
                );
            }
        }
    }

    public function testConnectorSdkIsBoundAndWiredIntoBoot(): void
    {
        // P3: connector discovery/loading is now live (see
        // ConnectorDiscovery's doc-comment for why P1 deliberately left
        // this unwired, and why it's wired here rather than inside
        // ConnectorLoader itself).
        $provider = file_get_contents($this->root().'/src/Providers/GamingHubPanelServiceProvider.php');

        foreach ([
            'singleton(ConnectorRegistry::class, InMemoryConnectorRegistry::class)',
            'singleton(ConnectorDiscovery::class)',
            'singleton(ConnectorLoader::class)',
            'ConnectorDiscovery::class)->discover()',
            'loadDiscoveredConnectors',
        ] as $needle) {
            self::assertStringContainsString($needle, $provider);
        }

        // Still no parallel capability system: whatever discovers/loads
        // Connectors must end by calling the real ConnectorLoader::load(),
        // which is the only thing that ever touches Core's real registries.
        self::assertStringContainsString('$loader->load($connector)', $provider);

        // Connector failures must never be reported via
        // recordRuntimeFailure() — that flips Panel's own compatible flag,
        // which is the wrong blast radius for "one connector had a
        // problem" (see the doc-comments at both call sites).
        $bootedCallback = substr($provider, (int) strpos($provider, '$this->app->booted(function'));
        $connectorSection = substr($bootedCallback, (int) strpos($bootedCallback, 'ConnectorDiscovery::class)->discover()'));
        self::assertStringNotContainsString('recordRuntimeFailure', substr($connectorSection, 0, (int) strpos($connectorSection, '});')));
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}

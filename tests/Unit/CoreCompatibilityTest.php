<?php

namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;

use Azuriom\Plugin\GamingHubPanel\Support\CoreCompatibility;
use PHPUnit\Framework\TestCase;

final class CoreCompatibilityTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }
    }

    public function testSupportedCoreVersionsAreAccepted(): void
    {
        foreach (['0.6.2', '0.7.0', '0.7.9'] as $version) {
            $result = $this->compatibility($this->metadata($version))->inspect();

            self::assertTrue($result->compatible, $version);
            self::assertSame($version, $result->coreVersion);
        }
    }

    public function testMissingAndIncompatibleCoreHaveSafeReasons(): void
    {
        $missing = $this->compatibility('/missing/core/plugin.json')->inspect();
        self::assertFalse($missing->compatible);
        self::assertSame('core_missing', $missing->code);
        self::assertNotSame('', $missing->reason);

        $incompatible = $this->compatibility($this->metadata('0.8.0'))->inspect();
        self::assertFalse($incompatible->compatible);
        self::assertSame('core_version_incompatible', $incompatible->code);
        self::assertStringContainsString('>=0.6.0 <0.8.0', $incompatible->reason);
    }

    public function testRequiredInterfacesUseAnInterfaceProbe(): void
    {
        $result = $this->compatibility($this->metadata('0.6.2'), interfaces: false)->inspect();

        self::assertFalse($result->compatible);
        self::assertSame('core_contract_missing', $result->code);
        self::assertStringContainsString('contract', $result->reason);
    }

    private function metadata(string $version): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ghp-core-');
        self::assertNotFalse($path);
        file_put_contents($path, json_encode(['id' => 'gaming-hub-core', 'version' => $version], JSON_THROW_ON_ERROR));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function compatibility(string $path, bool $interfaces = true, bool $classes = true): CoreCompatibility
    {
        return new class($path, $interfaces, $classes) extends CoreCompatibility {
            public function __construct(
                private readonly string $path,
                private readonly bool $interfaces,
                private readonly bool $classes,
            ) {
            }

            protected function pluginJsonPath(): ?string
            {
                return $this->path;
            }

            protected function interfaceAvailable(string $symbol): bool
            {
                return $this->interfaces;
            }

            protected function classAvailable(string $symbol): bool
            {
                return $this->classes;
            }
        };
    }
}

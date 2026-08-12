<?php

declare(strict_types=1);

// P1: Connector SDK proof harness. Follows the same dependency-light,
// no-real-Laravel-app house style as tests/run-provider-boot.php — stubs
// only the slice of Core's contracts the SDK actually touches, then
// exercises the real src/Connector/* classes (autoloaded below) against a
// single, obviously-synthetic example Connector. See docs/CONNECTOR_SDK.md.

namespace Azuriom\Plugin\GamingHubCore\Contracts {
    interface ProviderTypeRegistry
    {
        public function register(object $type): void;
        public function get(string $id): object;
        public function find(string $id): ?object;
        /** @return array<int, object> */
        public function all(): array;
    }

    interface CapabilityReaderRegistry
    {
        public function register(string $providerType, string $capability, string $readerClass): void;
        public function has(string $providerType, string $capability): bool;
        public function readerClass(string $providerType, string $capability): ?string;
    }

    interface CapabilityReader
    {
        public function read(object $server, object $provider): object;
        public function cacheTtl(): int;
    }
}

namespace Azuriom\Plugin\GamingHubCore\Data {
    final class SharedDataResult
    {
        public function __construct(public string $status = 'available', public array $data = [])
        {
        }
    }

    final class ProviderType
    {
        public function __construct(
            public string $id,
            public string $name,
            public string $description,
            public string $pluginId,
            public string $pluginName,
            public array $capabilities,
            public array $fields,
            public bool $available = true,
            public ?string $publicAttributionLabel = null,
        ) {
        }

        public function supports(string $capability): bool
        {
            return in_array($capability, $this->capabilities, true);
        }

        public function detached(): self
        {
            return clone $this;
        }
    }
}

namespace {
    use Azuriom\Plugin\GamingHubCore\Contracts\{CapabilityReader, CapabilityReaderRegistry, ProviderTypeRegistry};
    use Azuriom\Plugin\GamingHubCore\Data\{ProviderType, SharedDataResult};
    use Azuriom\Plugin\GamingHubPanel\Connector\ConnectorLoader;
    use Azuriom\Plugin\GamingHubPanel\Connector\Contracts\{ConnectorInterface, ConnectorRegistry};
    use Azuriom\Plugin\GamingHubPanel\Connector\Exceptions\{ConnectorRegistrationFailed, DuplicateConnector, UnknownConnector};
    use Azuriom\Plugin\GamingHubPanel\Connector\InMemoryConnectorRegistry;

    // Registered before any class below, since several of them implement
    // real autoloaded src/Connector/* interfaces (not hoistable — PHP
    // resolves those at the point of declaration, so the autoloader must
    // already be in place).
    $panelRoot = dirname(__DIR__);
    spl_autoload_register(function (string $class) use ($panelRoot): void {
        $prefix = 'Azuriom\\Plugin\\GamingHubPanel\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $path = $panelRoot.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }
    });

    final class TestProviderTypeRegistry implements ProviderTypeRegistry
    {
        /** @var array<string, ProviderType> */
        public array $types = [];
        public int $registerCalls = 0;

        public function register(object $type): void
        {
            ++$this->registerCalls;
            $this->types[$type->id] = $type;
        }

        public function get(string $id): object
        {
            return $this->types[$id] ?? throw new RuntimeException('unknown provider type: '.$id);
        }

        public function find(string $id): ?object
        {
            return $this->types[$id] ?? null;
        }

        public function all(): array
        {
            return array_values($this->types);
        }
    }

    final class TestCapabilityReaderRegistry implements CapabilityReaderRegistry
    {
        /** @var array<string, array<string, string>> */
        public array $readers = [];
        public int $registerCalls = 0;

        public function register(string $providerType, string $capability, string $readerClass): void
        {
            ++$this->registerCalls;
            $this->readers[$providerType][$capability] = $readerClass;
        }

        public function has(string $providerType, string $capability): bool
        {
            return isset($this->readers[$providerType][$capability]);
        }

        public function readerClass(string $providerType, string $capability): ?string
        {
            return $this->readers[$providerType][$capability] ?? null;
        }
    }

    // Deliberately not Pelican/Pterodactyl-shaped — a single fake capability
    // on a single fake provider type, so this can never be mistaken for a
    // real integration. See docs/CONNECTOR_SDK.md's worked example.
    final class ExampleManualReader implements CapabilityReader
    {
        public function read(object $server, object $provider): object
        {
            return new SharedDataResult('available', ['example.value' => 1]);
        }

        public function cacheTtl(): int
        {
            return 30;
        }
    }

    final class ExampleManualConnector implements ConnectorInterface
    {
        public function id(): string
        {
            return 'manual-example';
        }

        public function providerType(): ProviderType
        {
            return new ProviderType(
                id: 'manual-example',
                name: 'Manual Example',
                description: 'Synthetic Connector used only to prove the SDK, not a real integration.',
                pluginId: 'gaming-hub-panel',
                pluginName: 'Gaming Hub Panel',
                capabilities: ['example-status'],
                fields: [],
            );
        }

        public function readers(): array
        {
            return ['example-status' => ExampleManualReader::class];
        }
    }

    final class BrokenManualConnector implements ConnectorInterface
    {
        public function id(): string
        {
            return 'manual-example-broken';
        }

        public function providerType(): ProviderType
        {
            return new ProviderType(
                id: 'manual-example-broken',
                name: 'Manual Example (broken)',
                description: 'Declares a reader for a capability it does not advertise.',
                pluginId: 'gaming-hub-panel',
                pluginName: 'Gaming Hub Panel',
                capabilities: ['example-status'],
                fields: [],
            );
        }

        public function readers(): array
        {
            // 'example-metrics' is NOT in providerType()->capabilities above.
            return ['example-status' => ExampleManualReader::class, 'example-metrics' => ExampleManualReader::class];
        }
    }

    final class RivalManualConnector implements ConnectorInterface
    {
        public function id(): string
        {
            return 'manual-example';
        }

        public function providerType(): ProviderType
        {
            return new ProviderType(
                id: 'manual-example',
                name: 'Manual Example (rival)',
                description: 'Claims the same provider type id, owned by a different plugin.',
                pluginId: 'some-other-plugin',
                pluginName: 'Some Other Plugin',
                capabilities: ['example-status'],
                fields: [],
            );
        }

        public function readers(): array
        {
            return ['example-status' => ExampleManualReader::class];
        }
    }

    $failures = [];
    $check = static function (bool $ok, string $name) use (&$failures): void {
        echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
        if (! $ok) {
            $failures[] = $name;
        }
    };

    // --- ConnectorRegistry: register/has/get/all, duplicate rejection ---

    $connectors = new InMemoryConnectorRegistry();
    $check($connectors->all() === [], 'ConnectorRegistry starts empty');
    $check(! $connectors->has('manual-example'), 'ConnectorRegistry does not have an unregistered id');

    $example = new ExampleManualConnector();
    $connectors->register($example);
    $check($connectors->has('manual-example'), 'ConnectorRegistry has the registered id');
    $check($connectors->get('manual-example') === $example, 'ConnectorRegistry::get() returns the same instance');
    $check(count($connectors->all()) === 1 && $connectors->all()['manual-example'] === $example, 'ConnectorRegistry::all() is keyed by id()');

    try {
        $connectors->register(new ExampleManualConnector());
        $check(false, 'duplicate ConnectorRegistry::register() throws DuplicateConnector');
    } catch (DuplicateConnector) {
        $check(true, 'duplicate ConnectorRegistry::register() throws DuplicateConnector');
    }

    try {
        $connectors->get('does-not-exist');
        $check(false, 'ConnectorRegistry::get() throws UnknownConnector for a missing id');
    } catch (UnknownConnector) {
        $check(true, 'ConnectorRegistry::get() throws UnknownConnector for a missing id');
    }

    // --- ConnectorLoader::load(): produces a real Core ProviderType + reader ---

    $types = new TestProviderTypeRegistry();
    $readers = new TestCapabilityReaderRegistry();
    $loader = new ConnectorLoader($connectors, $types, $readers);

    $loader->load($example);
    $check(
        $types->find('manual-example') instanceof ProviderType,
        'load() registers a real Core ProviderType instance, not a parallel representation',
    );
    $check($types->find('manual-example')->capabilities === ['example-status'], 'registered ProviderType carries the Connector-declared capabilities');
    $check($readers->has('manual-example', 'example-status'), 'load() registers the declared reader');
    $check($readers->readerClass('manual-example', 'example-status') === ExampleManualReader::class, 'registered reader class matches the Connector-declared FQCN');

    // --- Idempotency ---

    $typeCallsBefore = $types->registerCalls;
    $readerCallsBefore = $readers->registerCalls;
    $loader->load($example);
    $check($types->registerCalls === $typeCallsBefore, 'repeated load() does not re-register the provider type');
    $check($readers->registerCalls === $readerCallsBefore, 'repeated load() does not re-register the reader');

    // --- Preflight-before-mutate: undeclared capability is rejected, nothing mutated ---

    $isolatedTypes = new TestProviderTypeRegistry();
    $isolatedReaders = new TestCapabilityReaderRegistry();
    $isolatedLoader = new ConnectorLoader($connectors, $isolatedTypes, $isolatedReaders);

    try {
        $isolatedLoader->load(new BrokenManualConnector());
        $check(false, 'a Connector declaring a reader for an undeclared capability is rejected');
    } catch (ConnectorRegistrationFailed) {
        $check(true, 'a Connector declaring a reader for an undeclared capability is rejected');
    }
    $check($isolatedTypes->registerCalls === 0 && $isolatedReaders->registerCalls === 0, 'rejection happens before either registry is mutated');

    // --- Ownership conflict ---

    try {
        $loader->load(new RivalManualConnector());
        $check(false, 'a Connector claiming an id already owned by another plugin is rejected');
    } catch (ConnectorRegistrationFailed) {
        $check(true, 'a Connector claiming an id already owned by another plugin is rejected');
    }

    // --- loadAll() over multiple registered Connectors ---

    final class SecondExampleConnector implements ConnectorInterface
    {
        public function id(): string
        {
            return 'manual-example-second';
        }

        public function providerType(): ProviderType
        {
            return new ProviderType(
                id: 'manual-example-second',
                name: 'Manual Example (second)',
                description: 'A second synthetic Connector, distinct id, used only to prove loadAll().',
                pluginId: 'gaming-hub-panel',
                pluginName: 'Gaming Hub Panel',
                capabilities: ['example-status'],
                fields: [],
            );
        }

        public function readers(): array
        {
            return ['example-status' => ExampleManualReader::class];
        }
    }

    $allTypes = new TestProviderTypeRegistry();
    $allReaders = new TestCapabilityReaderRegistry();
    $allConnectors = new InMemoryConnectorRegistry();
    $allConnectors->register(new ExampleManualConnector());
    $allConnectors->register(new SecondExampleConnector());
    (new ConnectorLoader($allConnectors, $allTypes, $allReaders))->loadAll();

    $check(
        $allTypes->find('manual-example') !== null && $allTypes->find('manual-example-second') !== null,
        'loadAll() registers every Connector known to the ConnectorRegistry',
    );

    exit($failures === [] ? 0 : 1);
}

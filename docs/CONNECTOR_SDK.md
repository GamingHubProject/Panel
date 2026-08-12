# Connector SDK (P1, discovery/loading wired in P3)

## What this is

Panel-side infrastructure for describing a provider type plus its
capability readers, and registering them into Gaming Hub Core's real
`ProviderTypeRegistry`/`CapabilityReaderRegistry`. It generalizes what
`GamingHubPanelServiceProvider::registerTypesAndReaders()` used to do by
hand for exactly two hardcoded types (Pelican, Pterodactyl) into a reusable
contract any number of Connectors can implement.

## What this is NOT

- **Not a new capability system.** Every Connector registration ends up as
  a real Core `ProviderType`, registered through Core's own
  `ProviderTypeRegistry` — no parallel representation exists anywhere in
  this SDK. Consumers still ask Core for meaning, never the SDK directly.
- **Not how Pelican/Pterodactyl currently work.** Their old hardcoded
  registration is still stubbed to empty (see
  `docs/CONNECTOR_MIGRATION_AUDIT.md`) — neither is registered as a real
  Connector package yet. Extracting a real Pelican Connector is P3.

## Distribution & discovery (P2/P3)

This SDK is wired into the live boot path and Manager can install a real,
standalone Connector package — this was the biggest open gap noted in P1
and is now closed:

- `GamingHubPanelServiceProvider::boot()` constructs a `ConnectorDiscovery`
  and, through it, a `ConnectorLoader`, and calls `load()` for every
  discovered Connector — see `ConnectorDiscovery::discover()` and the
  private `loadDiscoveredConnectors()` method on the service provider. P1
  originally left this unwired on purpose (no Connector package installer
  existed yet); P2 (Manager) and P3 (this discovery/loading wiring) closed
  that gap.
- Gaming Hub Manager installs a Connector package as type `'connector'`
  (`ExtensionManifestValidator::TYPES`) to `plugins-connectors/{id}`,
  sibling to Azuriom's own `plugins/` directory — it does not need to be a
  full Azuriom plugin.
- Panel discovers every enabled connector package there each boot:
  `ConnectorDiscovery::discover()` scans `plugins-connectors/`, skips any
  package missing the `.enabled` marker file (the same marker
  `ConnectorToggle` writes on enable/disable), then `require`s that
  package's `connector.php` bootstrap file and expects it to return a
  `ConnectorInterface` instance, which is registered into
  `ConnectorRegistry` and then loaded into Core's real registries via
  `ConnectorLoader::load()`.
- A connector package is still free to register itself in-process instead
  (e.g. from another already-installed Azuriom plugin's own `boot()`,
  calling `ConnectorRegistry::register()` directly) — discovery is an
  additional path, not the only one.
- Every discovery/load attempt is fault-isolated per connector: a missing
  or broken `connector.php`, a bootstrap file that doesn't return a
  `ConnectorInterface`, or a registration conflict is logged and skipped,
  and never prevents another connector — or Panel itself — from finishing
  boot (see the doc-comment on `ConnectorDiscovery` and the two separate
  `try`/`catch` blocks around this code in
  `GamingHubPanelServiceProvider::boot()`).

## Contract reference

```php
interface ConnectorInterface
{
    public function id(): string; // kebab-case, must equal providerType()->id
    public function providerType(): ProviderType; // Core's Data\ProviderType, detached
    /** @return array<string, class-string<CapabilityReader>> capability => reader FQCN */
    public function readers(): array;
}

interface ConnectorRegistry
{
    public function register(ConnectorInterface $connector): void; // throws DuplicateConnector
    public function has(string $id): bool;
    public function get(string $id): ConnectorInterface; // throws UnknownConnector
    /** @return array<string, ConnectorInterface> */
    public function all(): array;
}

final class ConnectorLoader
{
    public function __construct(
        ConnectorRegistry $connectors,
        ProviderTypeRegistry $types,   // Core's real registry
        CapabilityReaderRegistry $readers, // Core's real registry
    ) {}

    public function load(ConnectorInterface $connector): void; // throws ConnectorRegistrationFailed
    public function loadAll(): void; // load() every Connector known to $connectors
}
```

`ConnectorLoader::load()` mirrors the preflight-before-mutate discipline the
old hand-written code used: resolve `providerType()`, assert `id()` matches
it; check for an ownership conflict against an already-registered type of
the same id; validate every key of `readers()` is declared in the provider
type's own `capabilities` *before* touching either registry; register the
type if absent (idempotent); register each reader if not already present;
verify the postcondition (type + every declared capability's reader are
registered and owned by the expected plugin) before returning successfully.

## Minimal worked example

From `tests/run-connector-sdk.php` — deliberately not Pelican/Pterodactyl-shaped,
so it can never be mistaken for a real integration:

```php
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
            description: 'Synthetic Connector used only to prove the SDK.',
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

$connectors = new InMemoryConnectorRegistry();
$connectors->register(new ExampleManualConnector());

$loader = new ConnectorLoader($connectors, $types, $readers); // $types/$readers = Core's real registries
$loader->loadAll();
```

## Known open gap: per-type request/configuration validation

Core's registration contract has no hook for "this identifier is
well-formed for this provider type." That logic today lives entirely in
Panel's `ProviderConfiguration::validateIdentifier()` and
`SavePanelProviderRequest`, at a different point in the request lifecycle
than type/reader registration — not something `ConnectorInterface` as
designed here touches. This SDK deliberately does not solve it: a plausible
future shape is an optional second interface (e.g.
`ConnectorValidatesIdentifier`) that request-validation code could
`instanceof`-check a resolved Connector against, but guessing that shape now
— without a second real Connector to validate it against — is more likely
to guess wrong than to help. Left for P3 to resolve. See
`docs/CONNECTOR_MIGRATION_AUDIT.md`'s P3 checklist.

## Relationship to Core

Core needs zero changes to support this SDK. The only sanctioned
registration path Core exposes (per `docs/PROVIDERS.md` in the Core repo)
is "resolve `ProviderTypeRegistry`/`CapabilityReaderRegistry` from the
container and register a detached `ProviderType` during plugin boot." This
SDK is a reusable, validated way of doing exactly that for an arbitrary
number of Connectors instead of one hand-written call site per type — it
does not introduce, require, or depend on any dynamic-loader mechanism
Core doesn't already have.

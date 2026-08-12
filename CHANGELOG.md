# Changelog

## 0.1.010
- Version bump marking the Panel Connector foundation's P0/P1 milestone (stub Pelican/Pterodactyl out of the live registration path; Connector SDK contracts + `ConnectorLoader`) as complete — see the `0.2.2` entry below for the original P0/P1 change list. This release also carries the P3 discovery/loading wiring already on `main`: `ConnectorDiscovery` (scans `plugins-connectors/`, honors the `.enabled` marker, requires each package's `connector.php`) wired into `GamingHubPanelServiceProvider::boot()` alongside `ConnectorLoader`, closing the Manager -> Panel -> Connector loop end-to-end.

## 0.1.000
- Standardized versioning across the GamingHubProject repositories (Core, Panel, Manager) to a synchronized `0.1.000` baseline ahead of the initial public registry release. No functional change from `0.2.2` — this is a version-numbering reset only.

## 0.2.2
- **Breaking / destructive:** stopped registering Pelican and Pterodactyl as live Provider Types and Capability Readers with Gaming Hub Core (Panel Connector foundation roadmap phase P0 — see `docs/CONNECTOR_MIGRATION_AUDIT.md`). New provider creation no longer offers either type.
- **Breaking / destructive:** a new migration permanently deletes existing Pelican/Pterodactyl `ProviderInstance` rows, their linked credential/diagnostic rows, and all Panel Connections (plus discovered-server cache) of either type. This does not delete the underlying integration code, only the data — see the migration's doc-comment and `docs/CONNECTOR_MIGRATION_AUDIT.md` for the full rationale.
- Removed Panel's admin provider-form and public server-detail view overrides of Gaming Hub Core's pages, now that Panel owns no live Provider Type. The Panel Connections management UI remains available and functional; it is the intended landing spot for future Connectors.
- Added the Connector SDK foundation (`ConnectorInterface`, `ConnectorRegistry`, `ConnectorLoader`) under `src/Connector/` — not yet wired into the live boot path. See `docs/CONNECTOR_SDK.md`.
- Changed the mandatory Gaming Hub Core dependency in both `plugin.json` and `gaming-hub-extension.json` to the presence-only version constraint `*`.
- Removed Panel's internal Core minor-version ceiling while retaining runtime checks for the Core contracts and types Panel actually consumes.
- Verified the existing provider registration, Pelican/Pterodactyl readers, `server-status`, `metrics`, credential boundary, migrations, and PostgreSQL-oriented schema usage without redesigning integrations.
- Added dependency regression coverage for Core 0.7.170, 0.8.100, 0.8.110, 0.9.0, and 1.0.0 plus missing-Core failure behavior.

## 0.2.1
- Added verified compatibility with the reviewed Gaming Hub Core 0.7 line while retaining earlier supported Core behavior.
- Previously bounded compatibility to the reviewed pre-0.8 Core line.
- Updated Azuriom and Manager dependency metadata so Gaming Hub Core is declared consistently through the supported extension dependency map.
- Updated official repository metadata to `GamingHubProject/Panel`.
- Added `SharedDataGateway` to the compatibility probe because Panel directly consumes that Core contract.
- Updated compatibility, packaging, boot, provider-registration, and metadata regression tests for the reviewed Core line.

## 0.2.0
- Added reusable global Pelican and Pterodactyl Panel Connections.
- Added encrypted connection-level Application API keys and optional default Client API tokens.
- Added administrator Test and Discover Servers actions for each connection.
- Added normalized, refreshable discovered-server storage with stable identifiers and non-destructive missing-server handling.
- Reworked Panel provider forms to select a matching connection and discovered server instead of repeating Panel URL/Application credentials.
- Added optional per-server Client-token, timeout, and cache-TTL overrides.
- Added strict server-side connection/type/server ownership validation and an advanced manual identifier fallback.
- Preserved v0.1.x direct provider configurations and encrypted credentials until explicit migration.
- Added connection counts and unambiguous provider-type/configuration/health diagnostics wording.
- Fixed duplicate Settings and connection-page titles.
- Added focused dependency-light credential, discovery, mapping, lifecycle, security, and source-contract tests.

## 0.1.1
- Fixed the Core compatibility probe to use `interface_exists()` for Core contracts.
- Declared Gaming Hub Core as an Azuriom plugin dependency so Core loads first.
- Deferred provider and reader registration until the Laravel application is fully booted.
- Added safe boot diagnostics and administrator-visible compatibility status.
- Made route, provider, navigation, and duplicate-registration checks explicit and testable.

## 0.1.0
- Initial read-only Pelican and Pterodactyl providers.

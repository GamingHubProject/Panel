# Changelog

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

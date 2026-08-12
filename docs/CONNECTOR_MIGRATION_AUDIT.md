# Connector Migration Audit (P0)

## Purpose

Panel Connector foundation roadmap phase P0: Pelican and Pterodactyl are no
longer registered as live Provider Types / Capability Readers with Gaming
Hub Core, and their existing provider/connection data has been deleted.
This document is the inventory P3 (real Pelican Connector extraction, not
started here) consumes as its checklist. It is not itself an extraction
plan and does not sequence P2/P3/P4/P5.

## Status

- **Live registration**: disabled.
  `GamingHubPanelServiceProvider::providerDefinitions()` and
  `readerRegistrations()` return `[]`. New provider creation no longer
  offers `pelican`/`pterodactyl` as a type (Core's generic provider form is
  driven directly by `ProviderTypeRegistry::all()`).
- **View overrides**: removed. Both `View::prependNamespace(...)` calls
  (Core's admin provider form, Core's public server-detail view) were
  removed from `boot()`. Core's own generic views render for both pages now.
- **Existing provider/connection data**: deleted, not preserved. A
  migration (`database/migrations/2026_08_11_000000_dispose_pelican_pterodactyl_provider_data.php`)
  permanently removed pre-existing `pelican`/`pterodactyl`
  `ProviderInstance` rows, their linked `PanelCredential`/`ProviderDiagnostic`
  rows, and all Panel Connections (plus discovered-server cache) of either
  type. This is a **deliberate exception** to the platform's normal
  non-destructive-disable rule (see "Why the data was deleted" below), not
  the default pattern for future disable/uninstall flows.
- **Code on disk**: fully intact. Every Client/Reader/Normalizer/
  Controller/Request/View file described below is unchanged — only
  registration and data were touched.

## Why the data was deleted

Once registration is stubbed, `pelican`/`pterodactyl` no longer appear in
`ProviderTypeRegistry::all()`. Editing a pre-existing provider of either
type through Core's generic admin form — no longer intercepted by Panel's
own override, which used to route to `PanelProviderController` instead —
would render a `<select>` that doesn't list the provider's actual stored
type, and a save without deliberately re-picking a type risks silently
reassigning it to whatever the browser defaults to. Core's generic form
validator has also never been exercised against Pelican/Pterodactyl's
configuration shape (Panel intentionally skips Core's validator for these
types today). Deleting the data removed the hazard instead of guarding
against it.

## Inventory by extraction difficulty

### Trivially portable to a Connector (thin, type-specific only)
- `src/Clients/PelicanClient.php`, `src/Clients/PterodactylClient.php`
- `src/Readers/PelicanMetricsReader.php`, `PelicanServerStatusReader.php`,
  `PterodactylMetricsReader.php`, `PterodactylServerStatusReader.php`
- `src/Normalization/PelicanResponseNormalizer.php`,
  `PterodactylResponseNormalizer.php`

### Shared/generic infrastructure a Connector will depend on, not replace
- `src/Clients/AbstractPanelClient.php`
- `src/Readers/AbstractPanelReader.php`, `src/Readers/Concerns/Builds{Metrics,Status}Result.php`
- `src/Normalization/AbstractResponseNormalizer.php`, `StateMapper.php`
- `src/Services/PanelConnectionFactory.php`, `PanelDiscoveryService.php`,
  `PanelSnapshotService.php`, `PanelCredentialStore.php`,
  `PanelConnectionCredentialStore.php`, `DiagnosticRecorder.php`,
  `ConnectionHealthSummary.php`

### The one dispatch point (must be replaced by a Connector/registry lookup)
- `src/Services/PanelClientFactory.php` — currently
  `match($type){'pelican'=>...,'pterodactyl'=>...,default=>throw}`.

### Hardcoded type-branching to be replaced by Connector-declared behavior
- `src/Services/ProviderConfiguration.php::validateIdentifier()` — per-type
  regex branching.
- `src/Services/PanelMappingInspector.php` — `whereIn('provider_type', [...])`.
- `src/Http/Requests/SavePanelProviderRequest.php` — heaviest hardcoding in
  the codebase: `authorize()`, `withValidator()`, `providerData()`,
  `validateIdentifier()` all gate on a `['pelican','pterodactyl']` allowlist.
- `src/Http/Requests/SavePanelConnectionRequest.php` —
  `Rule::in(['pelican','pterodactyl'])`.
- `src/Controllers/Admin/ConnectionController.php::assertOwnership()`,
  `src/Controllers/Admin/DiagnosticsController.php` — same allowlist guard
  pattern.
- `src/Support/PanelBootDiagnostics.php` — dedicated
  `$pelicanRegistered`/`$pterodactylRegistered` booleans and an if/else
  chain in `markProviderRegistered()`.
- `src/Providers/GamingHubPanelServiceProvider.php` — the `PANEL_TYPES`
  constant and the `ProviderInstance::saved`/`::deleted` lifecycle hooks
  that key off it.

### Views to de-hardcode
- `resources/views/core-overrides/admin/providers/_form.blade.php` — a
  hardcoded `$panelTypes = ['pelican','pterodactyl']` literal plus ~120
  lines of per-type branching, versus a generic `$type->fields`-driven
  fallback that today only ever runs for a hypothetical non-panel type.
- `resources/views/core-overrides/admin/providers/index.blade.php` — the
  same allowlist gates Test/Diagnostics action buttons.
- `resources/views/admin/connections/partials/form.blade.php` — a literal
  `<select>` with two hardcoded `<option>`s.

Note: these three view files are no longer *activated* (the two
`View::prependNamespace(...)` calls that put them in front of Core's own
views were removed in P0), but they remain on disk unchanged. `_form` and
`index.blade.php` under `core-overrides/` are effectively dormant until a
future phase reintroduces some form of view override. The connections
`partials/form.blade.php` is still live — it's Panel's own native form for
managing Panel Connections, unrelated to Core's provider-admin pages.

### Fully generic either way
- Models/tables: `PanelConnectionProfile`, `DiscoveredPanelServer`,
  `PanelCredential`, `ProviderDiagnostic` — `panel_type` is a plain data
  column, not a schema/class discriminator. No migration is needed to add
  a Connector-based type value.
- `PanelConnectionsController`, `PanelConnectionActionsController`,
  `PanelConnectionCredentialsController` and `resources/views/admin/connections/*`
  — dispatch entirely through the `panel_type` column, no registry
  dependency. Left reachable throughout P0 (see Status above) as the
  intended future landing spot for Connectors installed via Manager.
- `routes/admin.php` — no provider-type literals in routing itself.

## What P3 will actually need to do (checklist, not started here)

- [ ] Package `PelicanClient` + `PelicanResponseNormalizer` + the two
      Pelican readers as a Connector implementing P1's `ConnectorInterface`.
- [ ] Move Pelican's `validateIdentifier()` regex somewhere the Connector
      can own it — `ConnectorInterface` as designed in P1 has no
      validation hook yet; see `docs/CONNECTOR_SDK.md`'s "known open gap".
- [ ] Replace `PanelClientFactory`'s `'pelican'` match arm with a
      Connector/registry lookup.
- [ ] Remove `'pelican'` from `PANEL_TYPES` and the hardcoded allowlists
      listed above, one at a time.
- [ ] De-hardcode `_form.blade.php`'s pelican branch once its fields are
      fully Connector-declared (only relevant once view overrides — or
      some future equivalent extension point — return).
- [ ] Repeat all of the above for Pterodactyl.
- [ ] Only after both: consider deleting or archiving this document.

## Non-goals of this document

Does not propose a Connector SDK shape — see `docs/CONNECTOR_SDK.md` (P1).
Does not sequence P2 (Manager connector package type), P3 (real Pelican
extraction), P4 (Pterodactyl extraction), or P5 (end-to-end proof).

# Architecture

## Two-level model

### Global Panel Connection

`PanelConnectionProfile` owns one reusable Pelican or Pterodactyl endpoint and its administrative/default runtime credentials:

- normalized base URL;
- encrypted Application API key;
- optional encrypted default Client API token;
- timeout, cache TTL, TLS verification, enabled state;
- last safe Application API test result.

### Discovered panel server

`DiscoveredPanelServer` stores only normalized administrative metadata returned by Application API discovery. A refresh upserts by `(connection_id, stable_identifier)`. Servers no longer returned are retained and marked unavailable instead of being deleted.

### Gaming Hub Server provider mapping

Gaming Hub Core continues to own provider instances. Panel configuration JSON stores only:

- `panel_connection_id`;
- `panel_server_identifier`;
- manual-fallback flag;
- optional public attribution label;
- optional timeout/cache overrides.

The provider-owned encrypted runtime slot is reused only for the optional per-server Client-token override. Global Application credentials are never copied into Core provider configuration or repeated per server.

## Credential resolution

Runtime Client credential resolution is:

1. provider Client-token override;
2. connection default Client API token;
3. `configuration_invalid`.

Application API keys are used only for connection testing and server discovery. They are not treated as Client API credentials.

## Adapters and Core integration

- `PelicanClient` and `PterodactylClient` remain distinct adapters with separate identifier validation and response normalization.
- Four readers register with Gaming Hub Core: Pelican/Pterodactyl × server-status/metrics.
- Both capabilities reuse one internally cached `PanelSnapshot` per provider.
- Public consumers use Gaming Hub Core's `SharedDataGateway`; concrete panel clients and connection records remain administration/runtime internals.
- Public output remains governed exclusively by Core's `PublicDataPolicyResolver` and `publicRead` contracts.

## Legacy compatibility

Provider configurations without `panel_connection_id` use the v0.1.x direct compatibility path. Their original Panel URL, API mode, identifier, TLS/timeout/cache values, and encrypted provider credentials are preserved. Migration is explicit and does not require provider recreation.

## Integrity behavior

- disabled connections cannot be selected for new mappings;
- an existing disabled mapping may only be preserved, not silently remapped;
- connection type must match provider type;
- a discovered server must belong to the selected connection;
- connection deletion is blocked while provider mappings reference it;
- panel type changes are blocked while mappings exist;
- changing a base URL marks cached discoveries unavailable until refreshed;
- mappings continue to use their stored stable identifier even when discovery data changes;
- incomplete mappings and unavailable runtime credentials return `configuration_invalid` instead of crashing public pages.

# Error and cache behavior

Safe categories are `authentication_failed`, `connection_failed`, `timeout`, `invalid_response`, `configuration_invalid`, `unsupported`, `unavailable`, and `unknown_error`.

- missing/disabled/mismatched connection, malformed identifier, or missing Client token → `configuration_invalid`;
- 401/403 → `authentication_failed`;
- inaccessible 404 server → `unavailable`;
- 429/5xx → `unavailable`;
- transport/TLS/DNS failure → `connection_failed`;
- timeout → `timeout`;
- malformed JSON/schema/metrics/discovery data → `invalid_response`.

A per-provider snapshot cache uses the resolved TTL:

1. provider override;
2. connection default;
3. extension global default.

Both Core capabilities reuse that snapshot. Provider saves, Client-token changes/removal, disable/delete events, and provider lifecycle changes invalidate the provider cache. Failures are not cached as successful snapshots.

Application API health is connection-owned. URL, panel type, timeout, TLS, or Application-key changes invalidate the previous test status. A changed base URL marks existing discovery records unavailable until a successful refresh; stored provider identifiers are not rewritten.

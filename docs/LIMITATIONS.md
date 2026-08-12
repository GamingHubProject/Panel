# Current limitations

- Read-only: no power controls, console, files, RCON, schedules, backups, databases, allocations, reinstall actions, or game-specific APIs.
- Discovery refresh is manual; scheduled polling is not included.
- A single default Client API token can only read servers visible to that Client account. Use a per-server override where different Client credentials are required.
- Application API access does not provide Client API runtime resources.
- No player counts are emitted because generic panel runtime resources do not reliably supply game player counts.
- Panel version is shown only when a safe recognized version header is available.
- API schema changes return `invalid_response` rather than guessed data.
- Pelican runtime routing currently requires a full UUID.
- The current Gaming Hub Core `MetricsData` contract does not carry uptime, so uptime is emitted through `ServerStatusData` only.
- Dependency-light tests use controlled responses and test doubles. Live panel, database, Docker, and HTTP checks remain target-installation verification steps.

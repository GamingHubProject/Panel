# Panel API endpoints

## Administrative connection operations

Both Pelican and Pterodactyl connections use:

- `GET /api/application/servers` — Application API test and paginated server discovery.

The Application API key is never used for runtime Client API resources.

## Pelican runtime

- `GET /api/client/servers/{full-uuid}` — selected server details and limits.
- `GET /api/client/servers/{full-uuid}/resources` — runtime state and resource metrics.

Pelican mappings store the full discovered server UUID as the stable runtime identifier.

## Pterodactyl runtime

- `GET /api/client/servers/{identifier}` — selected server details and limits.
- `GET /api/client/servers/{identifier}/resources` — runtime state and resource metrics.

Pterodactyl mappings store the discovered Client API identifier as the stable runtime identifier and retain the full UUID only as safe administrative metadata.

No HTML scraping, Wings calls, power actions, console operations, or game-specific APIs are used.

# Gaming Hub Panel 0.2.1

Gaming Hub Panel is a standalone, read-only Azuriom extension for Gaming Hub Core 0.6.x and 0.7.x. It registers separate Pelican and Pterodactyl provider types and publishes normalized `server-status` and `metrics` data through Core's Shared Data Gateway.

Version 0.2.0 introduced the two-level administration model. Version 0.2.1 is a compatibility/metadata patch for Gaming Hub Core 0.7.x and does not redesign that model.

The administration model remains:

1. Configure each Pelican or Pterodactyl panel once under **Administration → Gaming Hub Panel → Connections**.
2. Map each Gaming Hub Server provider to one enabled connection and one discovered panel server.

Application API keys are connection-owned and used only for administrator-authorized testing and discovery. Runtime reads resolve a Client API token from the provider override first, then the connection default. Existing v0.1.x direct provider configurations remain supported until explicitly migrated.

The extension does not implement power controls, console, files, RCON, schedules, backups, databases, allocations, game APIs, or scheduled polling.

See `INSTALL.md` and the files under `docs/` for architecture, security, testing, and release details.

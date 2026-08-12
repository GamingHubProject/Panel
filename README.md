# Gaming Hub Panel 0.1.010

Gaming Hub Panel is a standalone, read-only Azuriom extension for Gaming Hub Core. It hosts Panel Connectors (see `docs/CONNECTOR_SDK.md`) and publishes normalized `server-status` and `metrics` data through Core's Shared Data Gateway.

Version 0.1.010 marks completion of the Panel Connector foundation's P0/P1 milestone and carries the P3 discovery/loading wiring already on `main` — see `CHANGELOG.md` for details.

Version 0.1.000 was a focused dependency-contract correction. Gaming Hub Core remains mandatory, but Panel now declares it with the presence-only version constraint `*`, so current and future Core versions are not rejected solely by a Panel minor-version ceiling. Panel still verifies the Core contracts it actually consumes at runtime. No provider architecture, credential ownership, polling, routing, or package-management behavior is changed.

The administration model remains:

1. Configure each Pelican or Pterodactyl panel once under **Administration → Gaming Hub Panel → Connections**.
2. Map each Gaming Hub Server provider to one enabled connection and one discovered panel server.

Application API keys are connection-owned and used only for administrator-authorized testing and discovery. Runtime reads resolve a Client API token from the provider override first, then the connection default. Existing v0.1.x direct provider configurations remain supported until explicitly migrated.

The extension does not implement power controls, console, files, RCON, schedules, backups, databases, allocations, game APIs, package installation, package updates, package uninstall, or scheduled polling.

See `INSTALL.md` and the files under `docs/` for architecture, security, testing, and release details.

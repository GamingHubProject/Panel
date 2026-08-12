# Installation and upgrade

## Requirements

- Azuriom API 1.2.0 compatible installation
- PHP 8.2 or newer with cURL and JSON
- Gaming Hub Core installed and enabled before Gaming Hub Panel

## Upgrade to 0.2.2

1. Keep Gaming Hub Core installed and enabled.
2. Update Gaming Hub Panel with the complete `gaming-hub-panel-v0.2.2.zip` package. Do not merge source files into Gaming Hub Core or Azuriom core.
3. From the Azuriom root run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

4. Verify registration:

```bash
php artisan route:list | grep -i gaming-hub-panel
```

The output must include the Settings routes, Connections routes, connection credential/test/discovery routes, and existing provider actions.

5. Open **Administration → Gaming Hub Panel → Settings**. Confirm Panel 0.2.2, the installed Core version, routes registered, and both provider types registered.
6. Open **Administration → Gaming Hub Panel → Connections** and verify existing Pelican/Pterodactyl connections remain present.
7. Test an existing connection and verify its mapped provider still exposes `server-status` and `metrics`.

The existing v0.2.0 migrations are unchanged; v0.2.2 adds no database migration. Existing provider rows and encrypted credential rows are not rewritten or deleted. Legacy providers continue to run through the compatibility path until explicitly migrated from their edit form.

## New installation

1. Verify the ZIP against its SHA-256 checksum.
2. Install Gaming Hub Core first. Panel's Core dependency is mandatory and uses the version constraint `*`, meaning any installed Core version satisfies the package dependency.
3. Install the ZIP through Gaming Hub Manager or extract its single `gaming-hub-panel` root into Azuriom's `plugins` directory, then enable Gaming Hub Panel only while Core is enabled.
4. Run `php artisan migrate --force` and `php artisan optimize:clear`.
5. Create and test a global Panel Connection before creating Panel provider mappings.

## Connection workflow

**Administration → Gaming Hub Panel → Connections → Add Connection**

Configure:

- display name;
- Pelican or Pterodactyl type;
- panel base URL;
- Application API key;
- optional default Client API token;
- timeout, cache TTL, TLS verification, and enabled state.

Then use **Test Application API** and **Discover / refresh servers**.

## Provider mapping workflow

Open **Gaming Hub → Games → Game → Servers → Server → Providers → Create Provider** and choose Pelican or Pterodactyl.

Select:

- one enabled connection of the same type;
- one server discovered from that connection;
- optional per-server Client API token override;
- optional timeout/cache overrides;
- general provider name, priority, and enabled state.

The stable panel server identifier is derived and stored automatically. The normal flow does not require a repeated Panel URL, Application API key, or manually typed UUID.

## Legacy providers

A v0.1.x direct provider is labeled **Legacy direct configuration**. It can be left unchanged or explicitly migrated to a global connection from the same provider edit form. Its encrypted API/runtime tokens remain stored until the provider is migrated or the administrator removes them.

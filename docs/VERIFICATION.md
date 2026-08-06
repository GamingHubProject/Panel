# Release verification

## Executed in the build environment

The dependency-light aggregate suite was run with:

```bash
php tests/run-contracts.php
```

Result: **489 PASS lines, 0 FAIL lines**.

Focused runner results:

- Core compatibility/runtime failure harness: **20 checks passed**
- service-provider, routes, permissions, navigation, provider-type, reader, duplicate-registration, disabled-plugin, and missing-Core harness: **47 checks passed**
- v0.2 architecture, migrations, connection UI, provider mapping, legacy, diagnostics, title, security, and scope contracts: **153 checks passed**
- encrypted global credential preserve/replace/remove harness: **11 checks passed**
- Pelican/Pterodactyl Application API discovery, relationship include, pagination, normalization, and safe test-result harness: **19 checks passed**
- mapped-provider credential/timeout/cache/TLS inheritance and legacy runtime harness: **16 checks passed**
- URL/SSRF/redirect, state, metric, and typed-cache runtime harness: **25 checks passed**
- Blade directive nesting: **12 Blade files checked, balanced**
- PHP syntax: **all PHP, migration, route, test, language, and Blade-source files accepted by the release runner**

The suite specifically verifies:

- Gaming Hub Core `0.6.2` is accepted;
- missing or incompatible Core produces an administrator-safe reason without partial registration;
- both declared service providers boot through the supported lifecycle;
- Connections and Settings navigation entries are registered with separate permissions;
- all global connection, credential, test, discovery, server-list, provider mapping, diagnostics, and settings routes exist;
- Pelican and Pterodactyl remain limited to `server-status` and `metrics`;
- Application API keys and default Client tokens are encrypted before storage;
- blank secret fields preserve ciphertext, while explicit Replace and Remove actions work;
- discovery uses the Application credential and stores only normalized safe values;
- duplicate discovery refreshes existing rows and missing remote servers become unavailable rather than being deleted;
- provider mappings derive the stored identifier from the selected discovered-server record;
- connection/provider type mismatches, cross-connection server selections, disabled connections, and missing Client tokens fail as `configuration_invalid` or validation errors;
- runtime Client-token resolution is provider override, then connection default;
- timeout/cache resolution is provider override, connection default, then extension default;
- legacy v0.1.x direct providers and their encrypted credentials remain usable until explicitly migrated;
- normal v0.2 provider forms contain no Panel URL, Application API key, direct TLS field, or required manually typed UUID;
- Settings and Connections pages declare their layout title once and do not render a duplicate content heading;
- diagnostics distinguish provider-type registration, configured connections, healthy connections, and failed connections;
- no power, console, file, schedule, backup, RCON, or game-specific API capability was introduced.

## Not executed in this build environment

A complete Azuriom installation, database, Docker runtime, and real Panel credentials were not available here. Therefore the following are included as target-installation verification steps but are **not claimed as executed**:

- `vendor/bin/phpunit` against a complete Azuriom/Laravel vendor tree;
- live migration from v0.1.1 data;
- live encrypted database persistence through Azuriom’s application key;
- `php artisan route:list` on the target installation;
- browser rendering of the Azuriom administration and Core provider forms;
- live Pelican and Pterodactyl Application API tests and discovery;
- live Client API status and metrics reads;
- disable/re-enable, uninstall/reinstall data-retention policy, cache clear, and Docker restart.

Run the commands and administrator flow in `INSTALL.md` on the target installation before production use.

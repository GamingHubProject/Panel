# Testing

## Dependency-light release suite

Run from the plugin root:

```bash
php tests/run-contracts.php
```

The aggregate runner executes:

- PHP syntax and source contracts;
- Core 0.6.2 and 0.7.0 compatibility, Core 0.8.0 rejection, and safe failure diagnostics;
- real Panel service-provider boot against narrow Azuriom/Laravel doubles;
- route, permission, navigation, provider-type, reader, and duplicate-registration checks;
- global credential encryption/preserve/replace/remove behavior;
- Pelican and Pterodactyl Application API discovery normalization;
- provider mapping credential/timeout/cache inheritance and legacy compatibility;
- URL, redirect, state, metric, and cache-payload runtime checks;
- v0.2 database/form/diagnostics/security contracts;
- Blade directive-nesting checks across every plugin view.

Individual runners are:

```bash
php tests/run-boot.php
php tests/run-provider-boot.php
php tests/run-credentials.php
php tests/run-discovery.php
php tests/run-mapping.php
php tests/run-runtime.php
php tests/run-v020.php
php tests/run-blade.php
```

## PHPUnit

With an Azuriom/Laravel vendor tree available:

```bash
vendor/bin/phpunit -c plugins/gaming-hub-panel/phpunit.xml
```

The PHPUnit source suite is retained for integration into a full application test checkout. Dependency-light harnesses do not replace final live database, HTTP, enable/disable, Docker restart, or real-panel verification.

<?php

declare(strict_types=1);

use Azuriom\Plugin\GamingHubPanel\Support\{CoreCompatibility, PanelBootDiagnostics};

$root = dirname(__DIR__).'/src';
spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'Azuriom\\Plugin\\GamingHubPanel\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = $root.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($path)) {
        require $path;
    }
});

$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
    if (! $ok) {
        $failures[] = $name;
    }
};

$metadataFile = static function (array $metadata): string {
    $path = tempnam(sys_get_temp_dir(), 'ghp-core-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary metadata file.');
    }

    file_put_contents($path, json_encode($metadata, JSON_THROW_ON_ERROR));

    return $path;
};

$compatibility = static function (?string $path, bool $interfaces = true, bool $classes = true): CoreCompatibility {
    return new class($path, $interfaces, $classes) extends CoreCompatibility {
        public function __construct(
            private readonly ?string $path,
            private readonly bool $interfaces,
            private readonly bool $classes,
        ) {
        }

        protected function pluginJsonPath(): ?string
        {
            return $this->path;
        }

        protected function interfaceAvailable(string $symbol): bool
        {
            return $this->interfaces;
        }

        protected function classAvailable(string $symbol): bool
        {
            return $this->classes;
        }
    };
};

$coreFiles = [];
foreach (['0.7.170', '0.8.100', '0.8.110', '0.9.0', '1.0.0'] as $version) {
    $path = $metadataFile(['id' => 'gaming-hub-core', 'version' => $version]);
    $coreFiles[] = $path;
    $result = $compatibility($path)->inspect();
    $check($result->compatible, 'Gaming Hub Core '.$version.' accepted by presence-only dependency');
    $check($result->coreVersion === $version, 'Gaming Hub Core '.$version.' version retained');
    $check($result->code === 'compatible', 'Gaming Hub Core '.$version.' compatibility result');
}

$result = $compatibility('/path/that/does/not/exist')->inspect();
$check(! $result->compatible && $result->code === 'core_missing', 'missing Core handled safely');
$check($result->reason !== '', 'missing Core has administrator-safe reason');

$core08110 = $coreFiles[2];
$result = $compatibility($core08110, false, true)->inspect();
$check(! $result->compatible && $result->code === 'core_contract_missing', 'missing Core interface reported');
$check(str_contains($result->reason, 'contract'), 'missing interface reason is explicit');

$result = $compatibility($core08110, true, false)->inspect();
$check(! $result->compatible && $result->code === 'core_contract_missing', 'missing Core class reported');
$check(str_contains($result->reason, 'type'), 'missing class reason is explicit');

$status = new PanelBootDiagnostics();
$status->recordCompatibility($compatibility($core08110)->inspect());
$status->markRoutesRegistered();
$status->markProviderRegistered('pelican');
$status->markProviderRegistered('pterodactyl');
$snapshot = $status->snapshot();
$check($snapshot['panel_version'] === '0.1.010', 'diagnostics reports Panel version');
$check($snapshot['core_version'] === '0.8.110', 'diagnostics reports Core version');
$check($snapshot['routes_registered'] === true, 'diagnostics records routes');
$check($snapshot['pelican_provider_type_registered'] === true, 'diagnostics records Pelican');
$check($snapshot['pterodactyl_provider_type_registered'] === true, 'diagnostics records Pterodactyl');
$check($status->shouldReport('one failure'), 'first failure is reportable');
$check(! $status->shouldReport('one failure'), 'duplicate failure report suppressed');

$status->recordRuntimeFailure('core_services_unavailable', 'Core registries unavailable.');
$snapshot = $status->snapshot();
$check($snapshot['compatible'] === false, 'runtime registration failure is visible');
$check($snapshot['failure_reason'] === 'Core registries unavailable.', 'runtime failure reason retained');

foreach ($coreFiles as $path) { @unlink($path); }

exit($failures === [] ? 0 : 1);

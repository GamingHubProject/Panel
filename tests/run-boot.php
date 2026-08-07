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

$core062 = $metadataFile(['id' => 'gaming-hub-core', 'version' => '0.6.2']);
$result = $compatibility($core062)->inspect();
$check($result->compatible, 'Gaming Hub Core 0.6.2 accepted');
$check($result->coreVersion === '0.6.2', 'detected Core version retained');
$check($result->code === 'compatible', 'compatible result code');

$result = $compatibility('/path/that/does/not/exist')->inspect();
$check(! $result->compatible && $result->code === 'core_missing', 'missing Core handled safely');
$check($result->reason !== '', 'missing Core has administrator-safe reason');

$core070 = $metadataFile(['id' => 'gaming-hub-core', 'version' => '0.7.0']);
$result = $compatibility($core070)->inspect();
$check($result->compatible, 'Gaming Hub Core 0.7.0 accepted');
$check($result->coreVersion === '0.7.0', 'Core 0.7.0 version retained');
$check($result->code === 'compatible', 'Core 0.7.0 has no compatibility warning');

$core080 = $metadataFile(['id' => 'gaming-hub-core', 'version' => '0.8.0']);
$result = $compatibility($core080)->inspect();
$check(! $result->compatible && $result->code === 'core_version_incompatible', 'unreviewed Core 0.8.0 rejected clearly');
$check(str_contains($result->reason, '>=0.6.0 <0.8.0'), 'incompatible result states supported range');

$result = $compatibility($core062, false, true)->inspect();
$check(! $result->compatible && $result->code === 'core_contract_missing', 'missing Core interface reported');
$check(str_contains($result->reason, 'contract'), 'missing interface reason is explicit');

$result = $compatibility($core062, true, false)->inspect();
$check(! $result->compatible && $result->code === 'core_contract_missing', 'missing Core class reported');
$check(str_contains($result->reason, 'type'), 'missing class reason is explicit');

$status = new PanelBootDiagnostics();
$status->recordCompatibility($compatibility($core070)->inspect());
$status->markRoutesRegistered();
$status->markProviderRegistered('pelican');
$status->markProviderRegistered('pterodactyl');
$snapshot = $status->snapshot();
$check($snapshot['panel_version'] === '0.2.1', 'diagnostics reports Panel version');
$check($snapshot['core_version'] === '0.7.0', 'diagnostics reports Core version');
$check($snapshot['routes_registered'] === true, 'diagnostics records routes');
$check($snapshot['pelican_provider_type_registered'] === true, 'diagnostics records Pelican');
$check($snapshot['pterodactyl_provider_type_registered'] === true, 'diagnostics records Pterodactyl');
$check($status->shouldReport('one failure'), 'first failure is reportable');
$check(! $status->shouldReport('one failure'), 'duplicate failure report suppressed');

$status->recordRuntimeFailure('core_services_unavailable', 'Core registries unavailable.');
$snapshot = $status->snapshot();
$check($snapshot['compatible'] === false, 'runtime registration failure is visible');
$check($snapshot['failure_reason'] === 'Core registries unavailable.', 'runtime failure reason retained');

@unlink($core062);
@unlink($core070);
@unlink($core080);

exit($failures === [] ? 0 : 1);

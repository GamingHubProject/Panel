<?php

declare(strict_types=1);

use Azuriom\Plugin\GamingHubPanel\Support\CoreCompatibility;

$root = dirname(__DIR__);
$sourceRoot = $root.'/src';
spl_autoload_register(function (string $class) use ($sourceRoot): void {
    $prefix = 'Azuriom\\Plugin\\GamingHubPanel\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $path = $sourceRoot.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
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

$plugin = json_decode((string) file_get_contents($root.'/plugin.json'), true, flags: JSON_THROW_ON_ERROR);
$manifest = json_decode((string) file_get_contents($root.'/gaming-hub-extension.json'), true, flags: JSON_THROW_ON_ERROR);
$allText = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (! $file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)) {
        continue;
    }
    $allText .= @file_get_contents($file->getPathname()) ?: '';
}

$check(($plugin['version'] ?? null) === '0.1.010', 'Panel version is 0.1.010');
$check(($manifest['version'] ?? null) === '0.1.010', 'Gaming Hub manifest version is 0.1.010');
$check(($manifest['requires']['extensions']['gaming-hub-core'] ?? null) === '*', 'Gaming Hub manifest declares Core *');
$check(($plugin['dependencies']['gaming-hub-core'] ?? null) === '*', 'Azuriom plugin.json declares Core *');
$check(array_key_exists('gaming-hub-core', $plugin['dependencies'] ?? []), 'Core remains mandatory in plugin.json');
$check(array_key_exists('gaming-hub-core', $manifest['requires']['extensions'] ?? []), 'Core remains mandatory in Gaming Hub manifest');
$obsoleteConstraint = '>=0.6.0 ' . '<' . '0.8.0';
$check(! str_contains($allText, $obsoleteConstraint), 'obsolete Core constraint is absent');
$oldCeiling = '<' . '0.8.0';
$check(! str_contains($allText, $oldCeiling), 'no obsolete Core version ceiling remains');

$metadata = static function (string $version): string {
    $path = tempnam(sys_get_temp_dir(), 'ghp-v022-core-');
    if ($path === false) {
        throw new RuntimeException('Unable to create Core metadata fixture.');
    }
    file_put_contents($path, json_encode(['id' => 'gaming-hub-core', 'version' => $version], JSON_THROW_ON_ERROR));
    return $path;
};
$compatibility = static function (?string $path, bool $interfaces = true, bool $classes = true): CoreCompatibility {
    return new class($path, $interfaces, $classes) extends CoreCompatibility {
        public function __construct(
            private readonly ?string $path,
            private readonly bool $interfaces,
            private readonly bool $classes,
        ) {}
        protected function pluginJsonPath(): ?string { return $this->path; }
        protected function interfaceAvailable(string $symbol): bool { return $this->interfaces; }
        protected function classAvailable(string $symbol): bool { return $this->classes; }
    };
};

$temp = [];
foreach (['0.7.170', '0.8.100', '0.8.110', '0.9.0', '1.0.0'] as $version) {
    $path = $metadata($version);
    $temp[] = $path;
    $result = $compatibility($path)->inspect();
    $check($result->compatible, 'Core '.$version.' satisfies Panel dependency/contract probe');
}
$missing = $compatibility('/missing/core/plugin.json')->inspect();
$check(! $missing->compatible && $missing->code === 'core_missing', 'missing Core fails dependency/boot validation');

$core08110 = $metadata('0.8.110');
$temp[] = $core08110;
$check($compatibility($core08110)->inspect()->compatible, 'Panel install/boot compatibility accepts Core 0.8.110');
$check(! $compatibility($core08110, false, true)->inspect()->compatible, 'missing required Core contract prevents unsafe Panel boot');

$serviceProvider = (string) file_get_contents($root.'/src/Providers/GamingHubPanelServiceProvider.php');
foreach (['pelican', 'pterodactyl'] as $provider) {
    $check(str_contains($serviceProvider, "'{$provider}'"), ucfirst($provider).' provider registration remains present');
}
foreach (['server-status', 'metrics'] as $capability) {
    $check(str_contains($serviceProvider, "'{$capability}'"), $capability.' capability registration remains present');
}
$check(str_contains($serviceProvider, 'ProviderTypeRegistry::class'), 'ProviderTypeRegistry integration remains present');
$check(str_contains($serviceProvider, 'CapabilityReaderRegistry::class'), 'CapabilityReaderRegistry integration remains present');

$credentialSources = implode("\n", [
    (string) file_get_contents($root.'/src/Models/PanelCredential.php'),
    (string) file_get_contents($root.'/src/Models/PanelConnectionProfile.php'),
    (string) file_get_contents($root.'/src/Services/PanelConnectionCredentialStore.php'),
]);
$check(str_contains($credentialSources, 'Crypt::encryptString'), 'credentials remain encrypted and Panel-owned');
$check(! str_contains($serviceProvider, 'GamingHubCore\\Models\\PanelCredential'), 'no Core credential storage integration added');

$panelSource = '';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $panelSource .= file_get_contents($file->getPathname());
    }
}
foreach (['PackageInstaller', 'PackageManager', 'RegistryManager', 'gaming-hub-manager'] as $forbidden) {
    $check(! str_contains($panelSource, $forbidden), 'no Manager/package lifecycle functionality added: '.$forbidden);
}

$expectedMigrations = [
    '2026_08_05_100000_create_gaminghub_panel_credentials_table.php',
    '2026_08_05_101000_create_gaminghub_panel_diagnostics_table.php',
    '2026_08_06_000000_create_gaminghub_panel_connections_table.php',
    '2026_08_06_001000_create_gaminghub_panel_discovered_servers_table.php',
];
$actualMigrations = array_map('basename', glob($root.'/database/migrations/*.php') ?: []);
sort($actualMigrations);
sort($expectedMigrations);
$check($actualMigrations === $expectedMigrations, 'existing migration set unchanged');
foreach (glob($root.'/database/migrations/*.php') ?: [] as $migration) {
    $source = (string) file_get_contents($migration);
    $check(! preg_match('/\\bENUM\\b|ENGINE=|UNSIGNED BIGINT|AFTER `/i', $source), 'migration remains PostgreSQL-safe: '.basename($migration));
}

foreach ($temp as $path) {
    @unlink($path);
}

exit($failures === [] ? 0 : 1);

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
    if (! $ok) {
        $failures[] = $name;
    }
};

$plugin = json_decode((string) file_get_contents($root.'/plugin.json'), true);
$manifest = json_decode((string) file_get_contents($root.'/gaming-hub-extension.json'), true);
$registry = json_decode((string) file_get_contents($root.'/examples/extensions.json'), true);

$check(is_array($plugin), 'plugin.json valid JSON');
$check(is_array($manifest), 'gaming-hub-extension.json valid JSON');
$check(($plugin['id'] ?? null) === 'gaming-hub-panel', 'plugin identifier');
$check(($manifest['id'] ?? null) === ($plugin['id'] ?? null), 'matching identifiers');
$check(($manifest['version'] ?? null) === ($plugin['version'] ?? null), 'matching versions');
$check(($plugin['version'] ?? null) === '0.2.0', 'release version 0.2.0');
$check(($plugin['dependencies']['gaming-hub-core'] ?? null) === '^0.6.0', 'Azuriom Core dependency declared');
$check(($manifest['requires']['gaming-hub-core'] ?? null) === '^0.6.0', 'extension Core requirement declared');
$check(($manifest['type'] ?? null) === 'integration', 'integration type');
$check(($manifest['package']['plugin_directory'] ?? null) === 'gaming-hub-panel', 'single package root contract');
$check(($manifest['provides']['providers'] ?? []) === ['pelican', 'pterodactyl'], 'provider declarations');
$check(($manifest['provides']['capabilities'] ?? []) === ['server-status', 'metrics'], 'capability declarations');
$check(is_array($registry) && ($registry['extensions'][0]['id'] ?? null) === 'gaming-hub-panel', 'example registry entry');
$check(($registry['extensions'][0]['latest_version'] ?? null) === '0.2.0', 'registry version updated to 0.2.0');

$providers = $plugin['providers'] ?? [];
$expectedProviders = [
    '\\Azuriom\\Plugin\\GamingHubPanel\\Providers\\GamingHubPanelServiceProvider',
    '\\Azuriom\\Plugin\\GamingHubPanel\\Providers\\RouteServiceProvider',
];
$check($providers === $expectedProviders, 'plugin service-provider declarations exact');
foreach ($expectedProviders as $provider) {
    $relative = substr($provider, strlen('\\Azuriom\\Plugin\\GamingHubPanel\\'));
    $path = $root.'/src/'.str_replace('\\', '/', $relative).'.php';
    $check(is_file($path), 'declared provider class file exists: '.$relative);
}

$compatibility = (string) file_get_contents($root.'/src/Support/CoreCompatibility.php');
$check(str_contains($compatibility, 'interface_exists($symbol)'), 'Core contracts use interface_exists');
$check(! str_contains($compatibility, 'class_exists(\\Azuriom\\Plugin\\GamingHubCore\\Contracts\\ProviderTypeRegistry::class)'), 'broken interface class_exists probe removed');
$check(str_contains($compatibility, "VERSION_CONSTRAINT = '>=0.6.0 <0.7.0'"), 'Core compatibility range bounded');
$check(str_contains($compatibility, 'core_contract_missing'), 'missing Core contracts reported clearly');

$routeProvider = (string) file_get_contents($root.'/src/Providers/RouteServiceProvider.php');
$check(str_contains($routeProvider, 'public function loadRoutes(): void'), 'supported route-provider lifecycle method');
$check(str_contains($routeProvider, "->prefix('admin/'.\$this->plugin->id)"), 'admin route prefix uses plugin ID');
$check(str_contains($routeProvider, "->name(\$this->plugin->id.'.admin.')"), 'route names use plugin namespace');
$check(str_contains($routeProvider, 'markRoutesRegistered()'), 'route registration recorded');
$check(str_contains($routeProvider, 'Log::warning'), 'route compatibility failure logged');

$connectionController = (string) file_get_contents($root.'/src/Controllers/Admin/ConnectionController.php');
$providerController = (string) file_get_contents($root.'/src/Controllers/Admin/PanelProviderController.php');
$diagnosticsController = (string) file_get_contents($root.'/src/Controllers/Admin/DiagnosticsController.php');
foreach ([$connectionController, $providerController, $diagnosticsController] as $controllerSource) {
    $check(str_contains($controllerSource, 'Game $game'), 'implicit Game binding parameter matches {game}');
    $check(str_contains($controllerSource, 'Server $server'), 'implicit Server binding parameter matches {server}');
}
$check(str_contains($connectionController, 'ProviderInstance $provider'), 'connection provider binding matches {provider}');
$check(str_contains($diagnosticsController, 'ProviderInstance $provider'), 'diagnostics provider binding matches {provider}');
$check(str_contains($providerController, 'ProviderInstance $provider'), 'update provider binding matches {provider}');

$routes = (string) file_get_contents($root.'/routes/admin.php');
$check(str_contains($routes, "Route::get('/settings'"), 'GET settings route declared');
$check(str_contains($routes, "Route::put('/settings'"), 'PUT settings route declared');
foreach (['connections.index', 'connections.create', 'connections.store', 'connections.edit', 'connections.update', 'connections.destroy', 'connections.credentials.replace', 'connections.credentials.remove', 'connections.test', 'connections.discover', 'connections.servers', 'providers.store', 'providers.update', 'providers.credentials.replace', 'providers.credentials.remove', 'providers.test', 'providers.discover', 'providers.diagnostics'] as $name) {
    $check(str_contains($routes, "name('{$name}')"), 'provider route declared: '.$name);
}
foreach (['gaminghub-panel.connections.view', 'gaminghub-panel.connections.manage', 'gaminghub-panel.providers.configure', 'gaminghub-panel.connections.test', 'gaminghub-panel.servers.discover', 'gaminghub-panel.diagnostics.view', 'gaminghub-panel.settings.manage'] as $permission) {
    $check(str_contains($routes, $permission) || str_contains((string) file_get_contents($root.'/src/Providers/GamingHubPanelServiceProvider.php'), $permission), 'permission registered/protected: '.$permission);
}

$serviceProvider = (string) file_get_contents($root.'/src/Providers/GamingHubPanelServiceProvider.php');
$check(str_contains($serviceProvider, '$this->app->booted(function (): void'), 'Core registry work deferred until application booted');
$check(str_contains($serviceProvider, "bound(ProviderTypeRegistry::class)"), 'provider registry binding verified');
$check(str_contains($serviceProvider, "bound(CapabilityReaderRegistry::class)"), 'reader registry binding verified');
$check(str_contains($serviceProvider, 'Preflight both IDs'), 'duplicate provider registration preflighted');
$check(str_contains($serviceProvider, "['server-status', 'metrics']"), 'only implemented capabilities advertised');
$check(str_contains($serviceProvider, 'registerAdminNavigation()'), 'admin navigation registered');
$check(str_contains($serviceProvider, "Route::has('gaming-hub-panel.admin.settings.edit')"), 'admin navigation hidden without route');
$check(str_contains($serviceProvider, "'permission' => 'gaminghub-panel.settings.manage'"), 'settings navigation permission protected');
$check(str_contains($serviceProvider, 'ProviderInstance::saved'), 'provider save lifecycle hook registered');
$check(str_contains($serviceProvider, 'ProviderInstance::deleted'), 'provider delete lifecycle hook registered');

$settingsView = (string) file_get_contents($root.'/resources/views/admin/settings.blade.php');
$diagnosticsView = (string) file_get_contents($root.'/resources/views/admin/_boot-diagnostics.blade.php');
$check(str_contains($settingsView, "admin._boot-diagnostics"), 'boot diagnostics visible in admin settings');
foreach (['panel_version', 'core_version', 'routes_registered', 'pelican_provider_type_registered', 'pterodactyl_provider_type_registered', 'configured', 'healthy', 'failed', 'failure_reason'] as $field) {
    $check(str_contains($diagnosticsView, $field), 'safe boot diagnostic rendered: '.$field);
}
$check(! str_contains($diagnosticsView, 'token'), 'boot diagnostics expose no token fields');

$providerForm = (string) file_get_contents($root.'/resources/views/core-overrides/admin/providers/_form.blade.php');
$check(str_contains($providerForm, 'type="password" name="client_token_override"'), 'per-server Client override uses password input');
$check(! str_contains($providerForm, 'name="api_token"'), 'Application token removed from per-provider form');
$check(! str_contains($providerForm, 'name="runtime_token"'), 'legacy runtime field removed from normal provider form');
$check(! preg_match('/type="password"[^>]+value="/i', $providerForm), 'provider secrets never rendered as values');

$allPhp = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($allPhp as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $output = [];
    exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
    $check($code === 0, 'PHP syntax '.$file->getPathname());
}

$output = [];
exec('php '.escapeshellarg($root.'/tests/run-boot.php').' 2>&1', $output, $code);
foreach ($output as $line) {
    echo 'BOOT '.$line.PHP_EOL;
}
$check($code === 0, 'boot compatibility runtime harness');

$output = [];
exec('php '.escapeshellarg($root.'/tests/run-provider-boot.php').' 2>&1', $output, $code);
foreach ($output as $line) {
    echo 'PROVIDER '.$line.PHP_EOL;
}
$check($code === 0, 'service-provider lifecycle runtime harness');

foreach (['run-v020.php' => 'v0.2 architecture/source harness', 'run-credentials.php' => 'credential lifecycle runtime harness', 'run-discovery.php' => 'panel discovery normalization harness', 'run-mapping.php' => 'provider mapping resolution harness', 'run-runtime.php' => 'security and normalization runtime harness', 'run-blade.php' => 'Blade directive-nesting harness'] as $runner => $label) {
    $output = [];
    exec('php '.escapeshellarg($root.'/tests/'.$runner).' 2>&1', $output, $code);
    foreach ($output as $line) {
        echo strtoupper(pathinfo($runner, PATHINFO_FILENAME)).' '.$line.PHP_EOL;
    }
    $check($code === 0, $label);
}

exit($failures === [] ? 0 : 1);

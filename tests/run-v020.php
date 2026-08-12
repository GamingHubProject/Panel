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
$source = static fn (string $path): string => (string) file_get_contents($root.'/'.$path);

$plugin = json_decode($source('plugin.json'), true);
$manifest = json_decode($source('gaming-hub-extension.json'), true);
$check(($plugin['version'] ?? null) === '0.1.010', 'plugin version is 0.1.010');
$check(($manifest['version'] ?? null) === '0.1.010', 'extension manifest version is 0.1.010');
$check(($plugin['dependencies']['gaming-hub-core'] ?? null) === '*', 'Gaming Hub Core remains mandatory with unrestricted version');
$check(($manifest['provides']['capabilities'] ?? []) === ['server-status', 'metrics'], 'no capabilities beyond server-status and metrics');

$connectionsMigration = $source('database/migrations/2026_08_06_000000_create_gaminghub_panel_connections_table.php');
foreach ([
    'gaminghub_panel_connections', 'uuid', 'name', 'panel_type', 'base_url',
    'encrypted_application_token', 'encrypted_default_client_token', 'timeout', 'cache_ttl',
    'verify_tls', 'enabled', 'last_test_status', 'last_test_code', 'last_tested_at',
    'last_successful_test_at',
] as $field) {
    $check(str_contains($connectionsMigration, $field), 'connection migration contains '.$field);
}
$check(str_contains($connectionsMigration, "encrypted_application_token')->nullable()"), 'Application ciphertext supports explicit removal');

$serversMigration = $source('database/migrations/2026_08_06_001000_create_gaminghub_panel_discovered_servers_table.php');
foreach ([
    'gaminghub_panel_discovered_servers', 'connection_id', 'stable_identifier', 'uuid',
    'short_identifier', 'name', 'node_name', 'suspended', 'primary_allocation',
    'available', 'discovered_at', 'missing_since', 'metadata',
] as $field) {
    $check(str_contains($serversMigration, $field), 'discovered-server migration contains '.$field);
}
$check(str_contains($serversMigration, "unique(['connection_id', 'stable_identifier']"), 'discovery cache enforces connection/identifier uniqueness');
$check(str_contains($serversMigration, 'cascadeOnDelete()'), 'discovery cache follows connection deletion');

$connectionModel = $source('src/Models/PanelConnectionProfile.php');
$check(str_contains($connectionModel, "'encrypted_application_token',\n        'encrypted_default_client_token'"), 'connection model hides both ciphertexts');
$check(str_contains($connectionModel, 'hasApplicationToken'), 'connection exposes safe Application-token presence');
$check(str_contains($connectionModel, 'hasDefaultClientToken'), 'connection exposes safe Client-token presence');
$serverModel = $source('src/Models/DiscoveredPanelServer.php');
$check(str_contains($serverModel, "'metadata' => 'array'"), 'safe discovery metadata is typed');
$check(str_contains($serverModel, "'available' => 'boolean'"), 'discovered server availability is typed');

$credentialStore = $source('src/Services/PanelConnectionCredentialStore.php');
$check(str_contains($credentialStore, 'Crypt::encryptString'), 'global connection credentials encrypt before storage');
$check(str_contains($credentialStore, 'if (! $preserveBlank || filled($applicationToken))'), 'blank Application secret preserves stored value');
$check(str_contains($credentialStore, 'if (! $preserveBlank || filled($defaultClientToken))'), 'blank default Client secret preserves stored value');
$check(str_contains($credentialStore, 'replaceSlot'), 'explicit connection credential Replace action implemented');
$check(str_contains($credentialStore, 'public function remove'), 'explicit connection credential Remove action implemented');

$connectionsController = $source('src/Controllers/Admin/PanelConnectionsController.php');
$check(str_contains($connectionsController, "'uuid' => (string) Str::uuid()"), 'global connections receive stable UUIDs');
$check(str_contains($connectionsController, 'countForConnection'), 'connection deletion inspects active mappings');
$check(str_contains($connectionsController, 'Reassign or remove them first'), 'connection deletion is blocked while mapped');
$check(str_contains($connectionsController, 'Blank secret fields preserved'), 'connection update communicates blank-secret preservation');

$actionsController = $source('src/Controllers/Admin/PanelConnectionActionsController.php');
$check(str_contains($actionsController, 'testApplication'), 'connection Test action uses Application API');
$check(str_contains($actionsController, 'last_successful_test_at'), 'successful connection test timestamp recorded');
$check(str_contains($actionsController, "'connection_disabled'"), 'disabled connections fail server-list loading safely');
$check(! str_contains($actionsController, 'encrypted_application_token'), 'connection action responses never expose Application ciphertext');

$discoveryService = $source('src/Services/PanelDiscoveryService.php');
$check(str_contains($discoveryService, 'firstOrNew'), 'duplicate discovery refresh updates existing records');
$check(str_contains($discoveryService, "'available' => true"), 'rediscovered servers become available');
$check(str_contains($discoveryService, "'available' => false"), 'removed remote servers are retained as unavailable');
$check(str_contains($discoveryService, 'missing_since'), 'removed remote servers receive a safe missing timestamp');
$check(! str_contains($discoveryService, 'raw_response'), 'raw API responses are not stored');
$check(! str_contains($discoveryService, 'headers'), 'request headers are not stored in discovery cache');

foreach (['PelicanClient.php', 'PterodactylClient.php'] as $clientFile) {
    $client = $source('src/Clients/'.$clientFile);
    $check(str_contains($client, "'api/application/servers'"), $clientFile.' discovers through Application API');
    $check(str_contains($client, "'application'"), $clientFile.' explicitly selects Application credential');
    $check(str_contains($client, "'include' => 'node,allocations'"), $clientFile.' requests optional node/allocation relationships explicitly');
    $check(str_contains($client, "'stable_identifier'"), $clientFile.' emits a stable identifier');
    $check(str_contains($client, "'metadata' => ["), $clientFile.' emits only normalized metadata');
}

$providerConfiguration = $source('src/Services/ProviderConfiguration.php');
$overridePos = strpos($providerConfiguration, 'clientOverrideCiphertext');
$defaultPos = strpos($providerConfiguration, 'encrypted_default_client_token');
$check($overridePos !== false && $defaultPos !== false && $overridePos < $defaultPos, 'Client credential resolution checks provider override before connection default');
$check(str_contains($providerConfiguration, "throw new PanelApiException('configuration_invalid', 'No Client API token"), 'missing runtime Client credential returns configuration_invalid');
$check(str_contains($providerConfiguration, '! $profile->enabled'), 'disabled mapped connection returns configuration_invalid');
$check(str_contains($providerConfiguration, 'The mapped Panel Connection type does not match'), 'runtime validates connection/provider type');
$check(str_contains($providerConfiguration, 'private function legacy'), 'legacy direct-provider runtime path remains supported');
$check(str_contains($providerConfiguration, 'legacy: true'), 'legacy runtime configuration is explicitly marked');

$providerRequest = $source('src/Http/Requests/SavePanelProviderRequest.php');
$check(str_contains($providerRequest, "'configuration.panel_connection_id'"), 'provider request validates selected connection');
$check(str_contains($providerRequest, "'configuration.discovered_server_id'"), 'provider request validates selected discovered server');
$check(str_contains($providerRequest, '(int) $server->connection_id !== (int) $connection->id'), 'selected server must belong to selected connection');
$check(str_contains($providerRequest, 'The Panel Connection type does not match the provider type'), 'server-side type mismatch is rejected');
$check(str_contains($providerRequest, '! $connection->enabled && ! $preservingExistingConnection'), 'disabled connection cannot be selected for a new mapping');
$check(str_contains($providerRequest, "'configuration.manual_server_identifier'"), 'manual fallback uses a dedicated validated input');
$check(str_contains($providerRequest, 'known to belong to another Panel Connection'), 'manual identifier known on another connection is rejected');
$check(str_contains($providerRequest, '$this->validatedServer?->stable_identifier'), 'stored identifier is derived automatically from discovered server');
$check(str_contains($providerRequest, '$this->preserveLegacy = true'), 'legacy provider can be preserved without recreation');
$check(str_contains($providerRequest, "protected \$dontFlash = ['client_token_override']"), 'per-server Client override is never flashed');

$providerForm = $source('resources/views/core-overrides/admin/providers/_form.blade.php');
$check(str_contains($providerForm, 'Panel connection'), 'provider form contains Panel Connection dropdown');
$check(str_contains($providerForm, 'Panel server'), 'provider form contains discovered Panel Server dropdown');
$check(str_contains($providerForm, 'data-servers-url'), 'connection selection provides dynamic discovery endpoint');
$check(str_contains($providerForm, "fetch(url,{headers:{'Accept':'application/json'}})"), 'provider form loads discovered servers dynamically');
$check(str_contains($providerForm, 'Stored panel server identifier'), 'provider form displays stored identifier read-only');
$check(str_contains($providerForm, 'Advanced: enter server identifier manually'), 'manual fallback is available as an advanced option');
$check(str_contains($providerForm, '@if(!$manual) hidden @endif'), 'manual fallback is hidden by default');
$check(str_contains($providerForm, 'Client API token override'), 'provider form offers optional per-server Client override');
$check(str_contains($providerForm, 'Inherit connection default'), 'provider timeout/cache fields communicate inheritance');
$check(str_contains($providerForm, 'Legacy direct configuration'), 'legacy configuration is clearly labeled');
$check(! str_contains($providerForm, 'name="configuration[panel_url]"'), 'normal provider form no longer asks for Panel URL');
$check(! str_contains($providerForm, 'name="api_token"'), 'normal provider form no longer asks for Application API key');
$check(! str_contains($providerForm, 'name="configuration[ssl_verify]"'), 'provider mapping does not duplicate connection TLS policy');
$check(! preg_match('/type="password"[^>]+value="/i', $providerForm), 'provider form never renders a secret value');

$routes = $source('routes/admin.php');
foreach ([
    'connections.index', 'connections.create', 'connections.store', 'connections.edit',
    'connections.update', 'connections.destroy', 'connections.credentials.replace',
    'connections.credentials.remove', 'connections.test', 'connections.discover', 'connections.servers',
] as $route) {
    $check(str_contains($routes, "name('{$route}')"), 'connection route declared: '.$route);
}
foreach ([
    'gaminghub-panel.connections.view', 'gaminghub-panel.connections.manage',
    'gaminghub-panel.connections.test', 'gaminghub-panel.servers.discover',
    'gaminghub-panel.providers.configure', 'gaminghub-panel.diagnostics.view',
    'gaminghub-panel.settings.manage',
] as $permission) {
    $check(str_contains($routes, $permission) || str_contains($source('src/Providers/GamingHubPanelServiceProvider.php'), $permission), 'permission registered/protected: '.$permission);
}

$serviceProvider = $source('src/Providers/GamingHubPanelServiceProvider.php');
$check(str_contains($serviceProvider, "'gaming-hub-panel.admin.connections.index'"), 'Connections admin navigation registered');
$check(str_contains($serviceProvider, "'permission' => 'gaminghub-panel.connections.view'"), 'Connections navigation permission protected');
$check(str_contains($serviceProvider, "'gaming-hub-panel.admin.settings.edit'"), 'Settings admin navigation retained');
// P0 (Panel Connector foundation) stubbed providerDefinitions() to return [],
// so no ProviderConfigurationField is declared at all right now — including
// the v0.2.0 connection-mapping/stored-identifier fields these two checks
// used to assert. See docs/CONNECTOR_MIGRATION_AUDIT.md.
$check(! str_contains($serviceProvider, 'ProviderConfigurationField('), 'no provider configuration fields are declared while registration is stubbed (P0)');
$check(! str_contains($serviceProvider, "ProviderConfigurationField('panel_url'"), 'provider type no longer declares direct Panel URL');
$check(! str_contains($serviceProvider, "ProviderConfigurationField('api_token'"), 'provider type never declares Application token');

$bootView = $source('resources/views/admin/_boot-diagnostics.blade.php');
foreach ([
    'Routes registered', 'Pelican provider type registered', 'Pterodactyl provider type registered',
    'Configured Panel Connections', 'Healthy Panel Connections', 'Failed Panel Connections',
] as $label) {
    $check(str_contains($bootView, $label), 'diagnostics wording includes '.$label);
}
$check(! str_contains($bootView, 'Pelican registered</dt>'), 'ambiguous Pelican registration wording removed');
$check(! str_contains($bootView, 'Pterodactyl registered</dt>'), 'ambiguous Pterodactyl registration wording removed');

$settingsView = $source('resources/views/admin/settings.blade.php');
$check(substr_count($settingsView, 'Gaming Hub Panel Settings') === 1, 'Settings page title is declared once');
$check(! preg_match('/<h1[^>]*>\s*Gaming Hub Panel Settings/i', $settingsView), 'Settings content does not duplicate layout title');
foreach (['index', 'create', 'edit'] as $view) {
    $connectionView = $source('resources/views/admin/connections/'.$view.'.blade.php');
    $check(! preg_match('/<h1[^>]*>/i', $connectionView), 'Connections '.$view.' view does not duplicate layout title');
}

$connectionForm = $source('resources/views/admin/connections/partials/form.blade.php');
$check(str_contains($connectionForm, 'type="password" name="application_token"'), 'Application key input is secret');
$check(str_contains($connectionForm, 'type="password" name="default_client_token"'), 'default Client input is secret');
$check(! preg_match('/type="password"[^>]+value="/i', $connectionForm), 'connection form never renders stored secrets');
$check(str_contains($connectionForm, 'The Application API key does not replace a Client API token'), 'credential responsibilities are explicit');

$diagnosticsView = $source('resources/views/admin/diagnostics/show.blade.php');
$check(str_contains($diagnosticsView, 'Global Panel Connection mapping'), 'provider diagnostics distinguish mapped providers');
$check(str_contains($diagnosticsView, 'Legacy direct v0.1.x'), 'provider diagnostics distinguish legacy providers');
$check(str_contains($diagnosticsView, 'Connection Application key'), 'diagnostics show only Application-token presence');
$check(str_contains($diagnosticsView, 'Per-server Client override'), 'diagnostics show override presence');
$check(str_contains($diagnosticsView, 'Replace legacy API token'), 'legacy encrypted API token remains administratively manageable');
$check(! preg_match('/value="[^\"]*(?:ptla|ptlc|encrypted_|token)/i', $diagnosticsView), 'diagnostics never render credential values');

$allSource = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $allSource .= (string) file_get_contents($file->getPathname());
    }
}
foreach (['/power', '/console', '/files', '/schedules', '/backups', '/rcon'] as $forbidden) {
    $check(! str_contains(strtolower($allSource), $forbidden), 'forbidden capability absent: '.$forbidden);
}

exit($failures === [] ? 0 : 1);

<?php

namespace Azuriom\Plugin\GamingHubPanel\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Models\Permission;
use Azuriom\Plugin\GamingHubCore\Contracts\{CapabilityReaderRegistry, ProviderTypeRegistry};
use Azuriom\Plugin\GamingHubCore\Data\{ProviderConfigurationField, ProviderType};
use Azuriom\Plugin\GamingHubCore\Models\ProviderInstance;
use Azuriom\Plugin\GamingHubPanel\Contracts\HostResolver;
use Azuriom\Plugin\GamingHubPanel\Models\{PanelCredential, ProviderDiagnostic};
use Azuriom\Plugin\GamingHubPanel\Readers\{
    PelicanMetricsReader,
    PelicanServerStatusReader,
    PterodactylMetricsReader,
    PterodactylServerStatusReader
};
use Azuriom\Plugin\GamingHubPanel\Security\SystemHostResolver;
use Azuriom\Plugin\GamingHubPanel\Services\{DiagnosticRecorder, PanelClientFactory, PanelSnapshotService};
use Azuriom\Plugin\GamingHubPanel\Support\{CoreCompatibility, CoreCompatibilityResult, PanelBootDiagnostics};
use Illuminate\Support\Facades\{Log, Route, View};
use LogicException;
use Throwable;

final class GamingHubPanelServiceProvider extends BasePluginServiceProvider
{
    private const PANEL_TYPES = ['pelican', 'pterodactyl'];

    public function register(): void
    {
        $this->mergeConfigFrom(plugin_path($this->plugin->id.'/config/panel.php'), 'gaming-hub-panel');

        $this->app->singleton(CoreCompatibility::class);
        $this->app->singleton(PanelBootDiagnostics::class);
        $this->app->singleton(HostResolver::class, SystemHostResolver::class);
        $this->app->singleton(
            PanelSnapshotService::class,
            fn ($app) => new PanelSnapshotService(
                $app->make(PanelClientFactory::class),
                $app->make(DiagnosticRecorder::class),
                $app['cache.store'],
            ),
        );
    }

    public function boot(): void
    {
        $this->loadViews();
        $this->loadTranslations();
        $this->loadMigrations();

        Permission::registerPermissions([
            'gaminghub-panel.connections.view' => 'gaming-hub-panel::admin.permissions.connections_view',
            'gaminghub-panel.connections.manage' => 'gaming-hub-panel::admin.permissions.connections_manage',
            'gaminghub-panel.providers.configure' => 'gaming-hub-panel::admin.permissions.configure',
            'gaminghub-panel.connections.test' => 'gaming-hub-panel::admin.permissions.test',
            'gaminghub-panel.diagnostics.view' => 'gaming-hub-panel::admin.permissions.diagnostics',
            'gaminghub-panel.servers.discover' => 'gaming-hub-panel::admin.permissions.discover',
            'gaminghub-panel.settings.manage' => 'gaming-hub-panel::admin.permissions.settings',
        ]);

        $compatibility = $this->app->make(CoreCompatibility::class)->inspect();
        $this->recordCompatibility($compatibility);

        if (! $compatibility->compatible) {
            return;
        }

        $this->registerAdminNavigation();

        // Azuriom loads declared dependencies first, but the container bindings
        // exposed by Gaming Hub Core are only consumed after every provider has
        // completed booting. This avoids relying on provider declaration order.
        $this->app->booted(function (): void {
            $compatibility = $this->app->make(CoreCompatibility::class)->inspect();
            $this->recordCompatibility($compatibility);

            if (! $compatibility->compatible) {
                return;
            }

            if (! $this->app->bound(ProviderTypeRegistry::class)
                || ! $this->app->bound(CapabilityReaderRegistry::class)) {
                $this->recordRuntimeFailure(
                    'core_services_unavailable',
                    'Gaming Hub Core is compatible, but its provider registries are not available after application boot.',
                );

                return;
            }

            try {
                $this->registerTypesAndReaders();
                $this->registerProviderLifecycleHooks();

                // Core owns these namespaces. The override is activated only
                // after both provider types and their readers are available.
                View::prependNamespace('gaming-hub-core', plugin_path('gaming-hub-panel/resources/views/core-overrides'));
                View::prependNamespace('gaming-hub-core-runtime-v044', plugin_path('gaming-hub-panel/resources/views/core-runtime'));
            } catch (Throwable $exception) {
                $this->recordRuntimeFailure(
                    'panel_registration_failed',
                    'Gaming Hub Panel could not register its provider types and readers. Review the application log for the safe exception class.',
                    $exception,
                );
            }
        });
    }

    private function registerTypesAndReaders(): void
    {
        $types = $this->app->make(ProviderTypeRegistry::class);
        $readers = $this->app->make(CapabilityReaderRegistry::class);
        $definitions = $this->providerDefinitions();

        // Preflight both IDs before mutating either registry. A provider owned
        // by another plugin is a hard conflict rather than a partial install.
        foreach ($definitions as $id => $definition) {
            $existing = $types->find($id);
            if ($existing === null) {
                continue;
            }

            if ($existing->pluginId !== 'gaming-hub-panel'
                || ! $existing->supports('server-status')
                || ! $existing->supports('metrics')) {
                throw new LogicException(sprintf('Provider type "%s" is already owned by an incompatible registration.', $id));
            }
        }

        foreach ($definitions as $id => $definition) {
            if ($types->find($id) === null) {
                $types->register($definition);
            }
        }

        $registrations = [
            ['pelican', 'server-status', PelicanServerStatusReader::class],
            ['pelican', 'metrics', PelicanMetricsReader::class],
            ['pterodactyl', 'server-status', PterodactylServerStatusReader::class],
            ['pterodactyl', 'metrics', PterodactylMetricsReader::class],
        ];

        foreach ($registrations as [$providerType, $capability, $reader]) {
            if (! $readers->has($providerType, $capability)) {
                $readers->register($providerType, $capability, $reader);
            }
        }

        foreach (array_keys($definitions) as $providerType) {
            $registered = $types->find($providerType);
            if ($registered === null
                || $registered->pluginId !== 'gaming-hub-panel'
                || ! $readers->has($providerType, 'server-status')
                || ! $readers->has($providerType, 'metrics')) {
                throw new LogicException(sprintf('Provider type "%s" did not complete registration.', $providerType));
            }

            $this->app->make(PanelBootDiagnostics::class)->markProviderRegistered($providerType);
        }
    }

    /** @return array<string, ProviderType> */
    private function providerDefinitions(): array
    {
        $definitions = [];

        foreach (['pelican' => 'Pelican', 'pterodactyl' => 'Pterodactyl'] as $id => $name) {
            $definitions[$id] = new ProviderType(
                id: $id,
                name: $name,
                description: "Read-only {$name} Client API runtime status and resource metrics.",
                pluginId: 'gaming-hub-panel',
                pluginName: 'Gaming Hub Panel',
                capabilities: ['server-status', 'metrics'],
                fields: [
                    new ProviderConfigurationField('panel_connection_id', 'Panel Connection', 'integer', true, [], null, false, 'Select a global Gaming Hub Panel connection.'),
                    new ProviderConfigurationField('panel_server_identifier', 'Panel server identifier', 'string', true, [], 64, false, 'Stored automatically from server discovery.'),
                    new ProviderConfigurationField('manual_identifier', 'Manual identifier fallback', 'boolean', false),
                    new ProviderConfigurationField('public_attribution_label', 'Public attribution label', 'string', false, [], 100),
                    new ProviderConfigurationField('request_timeout', 'Timeout override (seconds)', 'integer', false),
                    new ProviderConfigurationField('cache_ttl', 'Cache TTL override (seconds)', 'integer', false),
                ],
                available: true,
                publicAttributionLabel: $name,
            );
        }

        return $definitions;
    }

    private function registerProviderLifecycleHooks(): void
    {
        ProviderInstance::saved(function (ProviderInstance $provider): void {
            $current = (string) $provider->provider_type;
            $original = (string) $provider->getOriginal('provider_type');
            if (! in_array($current, self::PANEL_TYPES, true)
                && ! in_array($original, self::PANEL_TYPES, true)) {
                return;
            }

            app(PanelSnapshotService::class)->invalidate((int) $provider->id);

            if (in_array($original, self::PANEL_TYPES, true)
                && ! in_array($current, self::PANEL_TYPES, true)) {
                $this->deleteExtensionRows((int) $provider->id);
            }
        });

        ProviderInstance::deleted(function (ProviderInstance $provider): void {
            if (! in_array((string) $provider->provider_type, self::PANEL_TYPES, true)) {
                return;
            }

            app(PanelSnapshotService::class)->invalidate((int) $provider->id);
            $this->deleteExtensionRows((int) $provider->id);
        });
    }

    private function deleteExtensionRows(int $providerId): void
    {
        try {
            PanelCredential::query()->where('provider_id', $providerId)->delete();
            ProviderDiagnostic::query()->where('provider_id', $providerId)->delete();
        } catch (Throwable) {
            // Foreign-key cascades normally perform this cleanup. The explicit
            // best-effort path also covers manual installations without the FK.
        }
    }

    private function recordCompatibility(CoreCompatibilityResult $result): void
    {
        $diagnostics = $this->app->make(PanelBootDiagnostics::class);
        $diagnostics->recordCompatibility($result);

        if (! $result->compatible && $diagnostics->shouldReport($result->reason)) {
            Log::warning('Gaming Hub Panel did not boot because Gaming Hub Core is unavailable or incompatible.', [
                'panel_plugin' => 'gaming-hub-panel',
                'core_plugin' => CoreCompatibility::CORE_PLUGIN_ID,
                'detected_core_version' => $result->coreVersion,
                'compatibility_code' => $result->code,
                'reason' => $result->reason,
            ]);
        }
    }

    private function recordRuntimeFailure(string $code, string $reason, ?Throwable $exception = null): void
    {
        $diagnostics = $this->app->make(PanelBootDiagnostics::class);
        $diagnostics->recordRuntimeFailure($code, $reason);

        if (! $diagnostics->shouldReport($reason)) {
            return;
        }

        Log::error('Gaming Hub Panel boot registration failed.', [
            'panel_plugin' => 'gaming-hub-panel',
            'compatibility_code' => $code,
            'reason' => $reason,
            'exception_class' => $exception === null ? null : $exception::class,
        ]);
    }

    protected function adminNavigation(): array
    {
        $status = $this->app->make(PanelBootDiagnostics::class)->snapshot();
        if (! $status['compatible'] || ! Route::has('gaming-hub-panel.admin.settings.edit')) {
            return [];
        }

        return [
            'gaming-hub-panel' => [
                'name' => 'Gaming Hub Panel',
                'type' => 'dropdown',
                'icon' => 'bi bi-hdd-network',
                'route' => 'gaming-hub-panel.admin.*',
                'items' => [
                    'gaming-hub-panel.admin.connections.index' => [
                        'name' => 'Connections',
                        'permission' => 'gaminghub-panel.connections.view',
                    ],
                    'gaming-hub-panel.admin.settings.edit' => [
                        'name' => 'Settings',
                        'permission' => 'gaminghub-panel.settings.manage',
                    ],
                ],
            ],
        ];
    }
}

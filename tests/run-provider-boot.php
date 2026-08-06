<?php

declare(strict_types=1);

namespace Azuriom\Plugin\GamingHubCore\Contracts {
    interface CapabilityReader {}
    interface ProviderInstances {}
    interface PublicDataPolicyResolver {}
    interface ProviderTypeRegistry
    {
        public function find(string $id): mixed;
        public function register(object $type): void;
    }
    interface CapabilityReaderRegistry
    {
        public function has(string $providerType, string $capability): bool;
        public function register(string $providerType, string $capability, string $reader): void;
    }
}

namespace Azuriom\Plugin\GamingHubCore\Data {
    final class MetricsData {}
    final class ProviderInstanceData {}
    final class ServerStatusData {}
    final class SharedDataResult {}

    final class ProviderConfigurationField
    {
        public function __construct(
            public string $key,
            public string $label,
            public string $type,
            public bool $required,
            public array $options = [],
            public ?int $maxLength = null,
            public bool $secret = false,
            public ?string $help = null,
        ) {
        }
    }

    final class ProviderType
    {
        public function __construct(
            public string $id,
            public string $name,
            public string $description,
            public string $pluginId,
            public string $pluginName,
            public array $capabilities,
            public array $fields,
            public bool $available,
            public string $publicAttributionLabel,
        ) {
        }

        public function supports(string $capability): bool
        {
            return in_array($capability, $this->capabilities, true);
        }
    }
}

namespace Azuriom\Plugin\GamingHubCore\Models {
    class Game {}
    class Server {}
    class ProviderInstance
    {
        public static array $savedCallbacks = [];
        public static array $deletedCallbacks = [];

        public static function saved(callable $callback): void
        {
            self::$savedCallbacks[] = $callback;
        }

        public static function deleted(callable $callback): void
        {
            self::$deletedCallbacks[] = $callback;
        }
    }
}

namespace Azuriom\Plugin\GamingHubCore\Validation {
    final class ProviderConfigurationValidator {}
}

namespace Azuriom\Models {
    final class Permission
    {
        public static array $permissions = [];

        public static function registerPermissions(array $permissions): void
        {
            self::$permissions = array_replace(self::$permissions, $permissions);
        }
    }
}

namespace Illuminate\Support\Facades {
    final class Log
    {
        public static array $warnings = [];
        public static array $errors = [];

        public static function warning(string $message, array $context = []): void
        {
            self::$warnings[] = [$message, $context];
        }

        public static function error(string $message, array $context = []): void
        {
            self::$errors[] = [$message, $context];
        }
    }

    final class View
    {
        public static array $namespaces = [];

        public static function prependNamespace(string $namespace, string $path): void
        {
            self::$namespaces[$namespace] = $path;
        }
    }

    final class RouteDefinition
    {
        public function __construct(public string $method, public string $uri, public array|string $action) {}

        public function whereIn(string $parameter, array $values): self
        {
            return $this;
        }

        public function name(string $name): self
        {
            Route::nameRoute($this, $name);

            return $this;
        }
    }

    final class RouteRegistrar
    {
        private ?string $prefix = null;
        private ?string $namePrefix = null;

        public function __construct(private array|string|null $middleware = null) {}

        public function prefix(string $prefix): self
        {
            $this->prefix = $prefix;

            return $this;
        }

        public function name(string $name): self
        {
            $this->namePrefix = $name;

            return $this;
        }

        public function group(callable|string $routes): void
        {
            Route::pushGroup($this->prefix, $this->namePrefix, $this->middleware);
            is_callable($routes) ? $routes() : require $routes;
            Route::popGroup();
        }

        public function get(string $uri, array|string $action): RouteDefinition
        {
            return Route::add('GET', $uri, $action, $this->middleware);
        }

        public function post(string $uri, array|string $action): RouteDefinition
        {
            return Route::add('POST', $uri, $action, $this->middleware);
        }

        public function put(string $uri, array|string $action): RouteDefinition
        {
            return Route::add('PUT', $uri, $action, $this->middleware);
        }

        public function delete(string $uri, array|string $action): RouteDefinition
        {
            return Route::add('DELETE', $uri, $action, $this->middleware);
        }
    }

    final class Route
    {
        public static array $routes = [];
        private static array $groups = [];

        public static function reset(): void
        {
            self::$routes = [];
            self::$groups = [];
        }

        public static function middleware(array|string $middleware): RouteRegistrar
        {
            return new RouteRegistrar($middleware);
        }

        public static function get(string $uri, array|string $action): RouteDefinition
        {
            return self::add('GET', $uri, $action);
        }

        public static function post(string $uri, array|string $action): RouteDefinition
        {
            return self::add('POST', $uri, $action);
        }

        public static function put(string $uri, array|string $action): RouteDefinition
        {
            return self::add('PUT', $uri, $action);
        }

        public static function delete(string $uri, array|string $action): RouteDefinition
        {
            return self::add('DELETE', $uri, $action);
        }

        public static function add(string $method, string $uri, array|string $action, array|string|null $middleware = null): RouteDefinition
        {
            $prefix = '';
            foreach (self::$groups as $group) {
                if ($group['prefix'] !== null) {
                    $prefix .= '/'.trim($group['prefix'], '/');
                }
            }

            return new RouteDefinition($method, trim($prefix.'/'.$uri, '/'), $action);
        }

        public static function nameRoute(RouteDefinition $definition, string $name): void
        {
            $prefix = '';
            foreach (self::$groups as $group) {
                $prefix .= $group['name'] ?? '';
            }
            self::$routes[$prefix.$name] = $definition;
        }

        public static function has(string $name): bool
        {
            return isset(self::$routes[$name]);
        }

        public static function pushGroup(?string $prefix, ?string $name, array|string|null $middleware): void
        {
            self::$groups[] = compact('prefix', 'name', 'middleware');
        }

        public static function popGroup(): void
        {
            array_pop(self::$groups);
        }
    }
}

namespace Azuriom\Extensions\Plugin {
    abstract class BasePluginServiceProvider
    {
        public object $plugin;

        public function __construct(public \TestApplication $app)
        {
            $this->plugin = (object) ['id' => 'gaming-hub-panel'];
        }

        protected function mergeConfigFrom(string $path, string $key): void {}
        protected function loadViews(): void {}
        protected function loadTranslations(): void {}
        protected function loadMigrations(): void {}

        protected function registerAdminNavigation(): void
        {
            $this->app->adminNavigationCallbacks[] = fn () => $this->adminNavigation();
        }

        protected function adminNavigation(): array
        {
            return [];
        }
    }

    abstract class BaseRouteServiceProvider
    {
        public object $plugin;

        public function __construct(public \TestApplication $app)
        {
            $this->plugin = (object) ['id' => 'gaming-hub-panel'];
        }

        abstract public function loadRoutes(): void;
    }
}

namespace {
    use Azuriom\Models\Permission;
    use Azuriom\Plugin\GamingHubCore\Contracts\{CapabilityReaderRegistry, ProviderTypeRegistry};
    use Azuriom\Plugin\GamingHubPanel\Providers\{GamingHubPanelServiceProvider, RouteServiceProvider};
    use Azuriom\Plugin\GamingHubPanel\Support\{CoreCompatibility, PanelBootDiagnostics};
    use Illuminate\Support\Facades\{Log, Route, View};

    final class TestProviderTypeRegistry implements ProviderTypeRegistry
    {
        public array $types = [];
        public int $registerCalls = 0;

        public function find(string $id): mixed
        {
            return $this->types[$id] ?? null;
        }

        public function register(object $type): void
        {
            ++$this->registerCalls;
            $this->types[$type->id] = $type;
        }
    }

    final class TestCapabilityReaderRegistry implements CapabilityReaderRegistry
    {
        public array $readers = [];
        public int $registerCalls = 0;

        public function has(string $providerType, string $capability): bool
        {
            return isset($this->readers[$providerType][$capability]);
        }

        public function register(string $providerType, string $capability, string $reader): void
        {
            ++$this->registerCalls;
            $this->readers[$providerType][$capability] = $reader;
        }
    }

    final class TestApplication implements ArrayAccess
    {
        public array $bindings = [];
        public array $instances = [];
        public array $bootedCallbacks = [];
        public array $adminNavigationCallbacks = [];
        private bool $isBooted = false;

        public function singleton(string $abstract, mixed $concrete = null): void
        {
            $this->bindings[$abstract] = $concrete ?? $abstract;
        }

        public function make(string $abstract): mixed
        {
            if (array_key_exists($abstract, $this->instances)) {
                return $this->instances[$abstract];
            }

            $concrete = $this->bindings[$abstract] ?? $abstract;
            $instance = $concrete instanceof Closure
                ? $concrete($this)
                : (is_string($concrete) ? new $concrete() : $concrete);
            $this->instances[$abstract] = $instance;

            return $instance;
        }

        public function bound(string $abstract): bool
        {
            return array_key_exists($abstract, $this->instances) || array_key_exists($abstract, $this->bindings);
        }

        public function booted(callable $callback): void
        {
            if ($this->isBooted) {
                $callback();
                return;
            }
            $this->bootedCallbacks[] = $callback;
        }

        public function runBootedCallbacks(): void
        {
            $this->isBooted = true;
            while ($callback = array_shift($this->bootedCallbacks)) {
                $callback();
            }
        }

        public function offsetExists(mixed $offset): bool
        {
            return $this->bound((string) $offset);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->make((string) $offset);
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            $this->instances[(string) $offset] = $value;
        }

        public function offsetUnset(mixed $offset): void
        {
            unset($this->instances[(string) $offset], $this->bindings[(string) $offset]);
        }
    }

    $panelRoot = dirname(__DIR__);
    $coreRoot = sys_get_temp_dir().'/ghp-core-boot-'.bin2hex(random_bytes(4));
    mkdir($coreRoot, 0777, true);
    file_put_contents($coreRoot.'/plugin.json', json_encode(['id' => 'gaming-hub-core', 'version' => '0.6.2'], JSON_THROW_ON_ERROR));

    $GLOBALS['ghp_plugin_paths'] = [
        'gaming-hub-panel' => $panelRoot,
        'gaming-hub-core' => $coreRoot,
    ];

    function plugin_path(string $path = ''): string
    {
        [$plugin, $relative] = array_pad(explode('/', $path, 2), 2, '');
        $base = $GLOBALS['ghp_plugin_paths'][$plugin] ?? sys_get_temp_dir().'/missing-plugin';

        return rtrim($base.'/'.$relative, '/');
    }

    function app(?string $abstract = null): mixed
    {
        $application = $GLOBALS['ghp_test_app'];

        return $abstract === null ? $application : $application->make($abstract);
    }

    spl_autoload_register(function (string $class) use ($panelRoot): void {
        $prefix = 'Azuriom\\Plugin\\GamingHubPanel\\';
        if (! str_starts_with($class, $prefix)) {
            return;
        }

        $path = $panelRoot.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
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

    Route::reset();
    $check(Route::$routes === [], 'disabled plugin contributes no routes when providers are not loaded');

    $application = new TestApplication();
    $types = new TestProviderTypeRegistry();
    $readers = new TestCapabilityReaderRegistry();
    $application->instances[ProviderTypeRegistry::class] = $types;
    $application->instances[CapabilityReaderRegistry::class] = $readers;
    $application->instances['cache.store'] = new stdClass();
    $GLOBALS['ghp_test_app'] = $application;

    $mainProvider = new GamingHubPanelServiceProvider($application);
    $routeProvider = new RouteServiceProvider($application);
    $mainProvider->register();
    $mainProvider->boot();
    $routeProvider->loadRoutes();
    $application->runBootedCallbacks();

    $check(Route::has('gaming-hub-panel.admin.settings.edit'), 'settings GET route registered by real route provider');
    $check(Route::has('gaming-hub-panel.admin.settings.update'), 'settings PUT route registered by real route provider');
    foreach (['connections.index', 'connections.create', 'connections.store', 'connections.edit', 'connections.update', 'connections.destroy', 'connections.credentials.replace', 'connections.credentials.remove', 'connections.test', 'connections.discover', 'connections.servers', 'providers.store', 'providers.update', 'providers.credentials.replace', 'providers.credentials.remove', 'providers.test', 'providers.discover', 'providers.diagnostics'] as $route) {
        $check(Route::has('gaming-hub-panel.admin.'.$route), 'provider route registered: '.$route);
    }

    $check(array_keys($types->types) === ['pelican', 'pterodactyl'], 'Pelican and Pterodactyl types registered');
    $check($types->types['pelican']->capabilities === ['server-status', 'metrics'], 'Pelican advertises only implemented capabilities');
    $check($types->types['pterodactyl']->capabilities === ['server-status', 'metrics'], 'Pterodactyl advertises only implemented capabilities');
    $check(count($readers->readers['pelican'] ?? []) === 2, 'Pelican readers registered');
    $check(count($readers->readers['pterodactyl'] ?? []) === 2, 'Pterodactyl readers registered');

    foreach (['gaminghub-panel.connections.view', 'gaminghub-panel.connections.manage', 'gaminghub-panel.settings.manage', 'gaminghub-panel.providers.configure', 'gaminghub-panel.connections.test', 'gaminghub-panel.servers.discover', 'gaminghub-panel.diagnostics.view'] as $permission) {
        $check(isset(Permission::$permissions[$permission]), 'permission registered: '.$permission);
    }

    $navigation = ($application->adminNavigationCallbacks[0])();
    $check(isset($navigation['gaming-hub-panel']['items']['gaming-hub-panel.admin.connections.index']), 'admin Connections navigation registered');
    $check($navigation['gaming-hub-panel']['items']['gaming-hub-panel.admin.connections.index']['permission'] === 'gaminghub-panel.connections.view', 'Connections navigation permission enforced');
    $check(isset($navigation['gaming-hub-panel']['items']['gaming-hub-panel.admin.settings.edit']), 'admin Settings navigation registered');
    $check($navigation['gaming-hub-panel']['items']['gaming-hub-panel.admin.settings.edit']['permission'] === 'gaminghub-panel.settings.manage', 'Settings navigation permission enforced');

    $status = $application->make(PanelBootDiagnostics::class)->snapshot();
    $check($status['compatible'] === true, 'boot diagnostics compatible');
    $check($status['routes_registered'] === true, 'boot diagnostics routes true');
    $check($status['pelican_provider_type_registered'] === true, 'boot diagnostics Pelican true');
    $check($status['pterodactyl_provider_type_registered'] === true, 'boot diagnostics Pterodactyl true');
    $check(isset(View::$namespaces['gaming-hub-core']), 'Core provider form namespace activated after registry success');

    $typeCalls = $types->registerCalls;
    $readerCalls = $readers->registerCalls;
    $mainProvider->boot();
    $check($types->registerCalls === $typeCalls, 'duplicate provider registration is safe');
    $check($readers->registerCalls === $readerCalls, 'duplicate reader registration is safe');

    Route::reset();
    unset($GLOBALS['ghp_plugin_paths']['gaming-hub-core']);
    $missingApplication = new TestApplication();
    $GLOBALS['ghp_test_app'] = $missingApplication;
    $missingMain = new GamingHubPanelServiceProvider($missingApplication);
    $missingRoute = new RouteServiceProvider($missingApplication);
    $missingMain->register();
    $missingMain->boot();
    $missingRoute->loadRoutes();
    $missingApplication->runBootedCallbacks();
    $check(Route::$routes === [], 'missing Core does not register partial routes');
    $missingStatus = $missingApplication->make(PanelBootDiagnostics::class)->snapshot();
    $check($missingStatus['compatible'] === false && $missingStatus['failure_reason'] !== null, 'missing Core records visible safe reason');
    $check(Log::$warnings !== [], 'missing Core writes administrator-safe warning');

    @unlink($coreRoot.'/plugin.json');
    @rmdir($coreRoot);

    exit($failures === [] ? 0 : 1);
}

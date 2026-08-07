<?php
declare(strict_types=1);

namespace Carbon {
    final class CarbonImmutable
    {
        public function __construct(private string $value = '2026-08-05T09:00:00+00:00') {}
        public static function now(): self { return new self(); }
        public static function parse(string $value): self { return new self($value); }
        public function toIso8601String(): string { return $this->value; }
    }
}

namespace {
    $root = dirname(__DIR__).'/src';
    spl_autoload_register(function (string $class) use ($root): void {
        $prefix = 'Azuriom\\Plugin\\GamingHubPanel\\';
        if (! str_starts_with($class, $prefix)) return;
        $path = $root.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) require $path;
    });

    use Azuriom\Plugin\GamingHubPanel\Contracts\HostResolver;
    use Azuriom\Plugin\GamingHubPanel\Data\PanelSnapshot;
    use Azuriom\Plugin\GamingHubPanel\Exceptions\{PanelApiException, UnsafePanelUrl};
    use Azuriom\Plugin\GamingHubPanel\Normalization\{PelicanResponseNormalizer, PterodactylResponseNormalizer, StateMapper};
    use Azuriom\Plugin\GamingHubPanel\Security\PanelUrlGuard;
    use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;
    use Carbon\CarbonImmutable;

    $failures = [];
    $check = function (bool $ok, string $name) use (&$failures): void {
        echo ($ok ? 'PASS ' : 'FAIL ').$name.PHP_EOL;
        if (! $ok) $failures[] = $name;
    };

    $resolver = new class implements HostResolver {
        public function resolve(string $host): array
        {
            return match ($host) {
                'panel.example' => ['93.184.216.34'],
                'evil.example' => ['93.184.216.35'],
                'panel.lan' => ['192.168.1.10'],
                '::1' => ['::1'],
                default => [],
            };
        }
    };
    $settings = new class extends PanelSettings {
        public function __construct(private bool $private = false, private bool $http = false) {}
        public function all(): array
        {
            return [
                'default_timeout' => 8,
                'default_ttl' => 15,
                'allow_private_hosts' => $this->private,
                'allow_insecure_http' => $this->http,
                'prerelease_warnings' => true,
            ];
        }
    };
    $guard = new PanelUrlGuard($resolver, $settings);
    $check($guard->validate('https://panel.example')->url === 'https://panel.example', 'public HTTPS URL accepted');

    try {
        $guard->validate('https://user:pass@panel.example');
        $check(false, 'embedded credentials rejected');
    } catch (UnsafePanelUrl) {
        $check(true, 'embedded credentials rejected');
    }

    try {
        $guard->validate('http://panel.example');
        $check(false, 'HTTP rejected by default');
    } catch (UnsafePanelUrl) {
        $check(true, 'HTTP rejected by default');
    }

    try {
        $guard->validate('https://panel.lan');
        $check(false, 'private host requires trust');
    } catch (UnsafePanelUrl) {
        $check(true, 'private host requires trust');
    }

    $privateSettings = new class extends PanelSettings {
        public function all(): array
        {
            return ['default_timeout'=>8, 'default_ttl'=>15, 'allow_private_hosts'=>true, 'allow_insecure_http'=>false, 'prerelease_warnings'=>true];
        }
    };
    $privateGuard = new PanelUrlGuard($resolver, $privateSettings);
    $check($privateGuard->validate('https://panel.lan')->host === 'panel.lan', 'trusted private host accepted');
    $check($privateGuard->validate('https://[::1]')->url === 'https://[::1]', 'trusted IPv6 literal normalized');

    $origin = $guard->validate('https://panel.example/base');
    try {
        $guard->validateRedirect($origin, 'https://evil.example/api', 'https://panel.example/base/api/client');
        $check(false, 'cross-host redirect rejected');
    } catch (UnsafePanelUrl) {
        $check(true, 'cross-host redirect rejected');
    }
    $redirect = $guard->validateRedirect($origin, '../resources', 'https://panel.example/base/api/client/server');
    $check($redirect->url === 'https://panel.example/base/api/resources', 'relative redirect uses current request path');

    $mapper = new StateMapper();
    $check($mapper->map('running')[0] === 'online', 'running maps online');
    $check($mapper->map('offline')[0] === 'offline', 'offline maps offline');
    $check($mapper->map('restoring_backup')[0] === 'maintenance', 'restoring maps maintenance');
    $check($mapper->map('alien')[0] === 'unknown', 'unknown state maps unknown');
    $check($mapper->map('running', true)[0] === 'maintenance', 'suspension overrides runtime');

    $server = ['attributes' => ['uuid'=>'123e4567-e89b-42d3-a456-426614174000', 'identifier'=>'abcd1234', 'name'=>'Test server', 'limits'=>['memory'=>1024], 'status'=>null]];
    $resources = ['attributes' => ['current_state'=>'running', 'is_suspended'=>false, 'resources'=>['cpu_absolute'=>125.5, 'memory_bytes'=>1048576, 'disk_bytes'=>2048, 'uptime'=>15000]]];
    foreach ([new PelicanResponseNormalizer($mapper), new PterodactylResponseNormalizer($mapper)] as $normalizer) {
        $snapshot = $normalizer->snapshot($server, $resources);
        $check($snapshot->state === 'online', get_class($normalizer).' state');
        $check($snapshot->cpuPercent === 125.5, get_class($normalizer).' CPU');
        $check($snapshot->memoryLimitBytes === 1073741824, get_class($normalizer).' MiB to bytes');
        $check($snapshot->uptimeSeconds === 15, get_class($normalizer).' uptime milliseconds to seconds');
    }

    try {
        (new PelicanResponseNormalizer($mapper))->snapshot($server, ['attributes'=>['current_state'=>'running', 'resources'=>['cpu_absolute'=>-1, 'memory_bytes'=>0, 'disk_bytes'=>0, 'uptime'=>0]]]);
        $check(false, 'negative metrics rejected');
    } catch (PanelApiException $e) {
        $check($e->category === 'invalid_response', 'negative metrics rejected');
    }

    try {
        (new PelicanResponseNormalizer($mapper))->snapshot($server, ['attributes'=>['current_state'=>'running', 'resources'=>['cpu_absolute'=>1, 'memory_bytes'=>'1.5', 'disk_bytes'=>0, 'uptime'=>0]]]);
        $check(false, 'fractional byte metrics rejected');
    } catch (PanelApiException $e) {
        $check($e->category === 'invalid_response', 'fractional byte metrics rejected');
    }

    $snapshot = new PanelSnapshot('online', null, 'Test', 125.5, 1, 2, 3, 4, CarbonImmutable::now());
    $cached = PanelSnapshot::fromCacheArray($snapshot->toCacheArray());
    $check($cached?->state === 'online' && $cached?->memoryLimitBytes === 2, 'typed snapshot cache round-trip');
    $bad = $snapshot->toCacheArray();
    $bad['state'] = 'running';
    $check(PanelSnapshot::fromCacheArray($bad) === null, 'invalid cached state rejected');

    exit($failures === [] ? 0 : 1);
}

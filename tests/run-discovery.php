<?php

declare(strict_types=1);

namespace Azuriom\Plugin\GamingHubPanel\Http {
    use Azuriom\Plugin\GamingHubPanel\Data\{PanelConnection, PanelHttpResponse};

    class SafePanelHttpClient
    {
        /** @var list<PanelHttpResponse|\Throwable> */
        public array $queue = [];
        /** @var list<array{path:string,query:array<string,mixed>,credential:string}> */
        public array $calls = [];

        public function get(
            PanelConnection $connection,
            string $path,
            array $query = [],
            string $credential = 'client',
        ): PanelHttpResponse {
            $this->calls[] = compact('path', 'query', 'credential');
            $next = array_shift($this->queue);
            if ($next instanceof \Throwable) {
                throw $next;
            }
            if (! $next instanceof PanelHttpResponse) {
                throw new \RuntimeException('No queued response.');
            }

            return $next;
        }
    }
}

namespace {
    use Azuriom\Plugin\GamingHubPanel\Clients\{PelicanClient, PterodactylClient};
    use Azuriom\Plugin\GamingHubPanel\Data\{PanelConnection, PanelHttpResponse};
    use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
    use Azuriom\Plugin\GamingHubPanel\Http\SafePanelHttpClient;
    use Azuriom\Plugin\GamingHubPanel\Normalization\{PelicanResponseNormalizer, PterodactylResponseNormalizer, StateMapper};

    $root = dirname(__DIR__).'/src';
    spl_autoload_register(static function (string $class) use ($root): void {
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
    $response = static fn (array $payload): PanelHttpResponse => new PanelHttpResponse(
        200,
        [],
        json_encode($payload, JSON_THROW_ON_ERROR),
        1,
    );
    $connection = static fn (string $type): PanelConnection => new PanelConnection(
        providerId: null,
        connectionId: 7,
        panelType: $type,
        baseUrl: 'https://panel.example',
        serverIdentifier: '',
        encryptedApplicationToken: 'application-ciphertext',
        encryptedClientToken: 'client-ciphertext',
        attributionLabel: 'Main Panel',
        timeout: 8,
        verifySsl: true,
        cacheTtl: 15,
    );

    $pelicanHttp = new SafePanelHttpClient();
    $pelicanHttp->queue[] = $response([
        'data' => [[
            'attributes' => [
                'uuid' => '123e4567-e89b-42d3-a456-426614174000',
                'identifier' => 'abcd1234',
                'name' => 'Pelican Server',
                'suspended' => false,
                'status' => 'running',
                'node_name' => 'Node A',
            ],
            'relationships' => [
                'allocations' => ['data' => [[
                    'attributes' => ['ip' => '203.0.113.10', 'port' => 8211, 'is_default' => true],
                ]]],
            ],
        ]],
        'meta' => ['pagination' => ['current_page' => 1, 'total_pages' => 2]],
    ]);
    $pelican = new PelicanClient($pelicanHttp, new PelicanResponseNormalizer(new StateMapper()));
    $pelicanPage = $pelican->discover($connection('pelican'), 1, 100);
    $check($pelicanHttp->calls[0]['path'] === 'api/application/servers', 'Pelican discovery uses Application API endpoint');
    $check(($pelicanHttp->calls[0]['query']['include'] ?? null) === 'node,allocations', 'Pelican discovery requests only safe optional node/allocation relationships');
    $check($pelicanHttp->calls[0]['credential'] === 'application', 'Pelican discovery uses Application credential');
    $check($pelicanPage->servers[0]['stable_identifier'] === '123e4567-e89b-42d3-a456-426614174000', 'Pelican stable identifier is full UUID');
    $check($pelicanPage->servers[0]['short_identifier'] === 'abcd1234', 'Pelican short identifier normalized');
    $check($pelicanPage->servers[0]['node_name'] === 'Node A', 'Pelican node name normalized');
    $check($pelicanPage->servers[0]['primary_allocation'] === '203.0.113.10:8211', 'Pelican safe primary allocation normalized');
    $check($pelicanPage->hasMore, 'Pelican pagination retained');
    $check(array_keys($pelicanPage->servers[0]) === [
        'stable_identifier', 'name', 'uuid', 'short_identifier', 'node_name', 'suspended', 'primary_allocation', 'metadata',
    ], 'Pelican discovery exposes only safe normalized keys');

    $pteroHttp = new SafePanelHttpClient();
    $pteroHttp->queue[] = $response([
        'data' => [[
            'attributes' => [
                'uuid' => '223e4567-e89b-42d3-a456-426614174000',
                'identifier' => 'efgh5678',
                'name' => 'Pterodactyl Server',
                'suspended' => true,
                'status' => null,
            ],
        ]],
        'meta' => ['pagination' => ['current_page' => 1, 'total_pages' => 1]],
    ]);
    $ptero = new PterodactylClient($pteroHttp, new PterodactylResponseNormalizer(new StateMapper()));
    $pteroPage = $ptero->discover($connection('pterodactyl'), 1, 25, 'Pterodactyl');
    $check($pteroHttp->calls[0]['path'] === 'api/application/servers', 'Pterodactyl discovery uses Application API endpoint');
    $check(($pteroHttp->calls[0]['query']['include'] ?? null) === 'node,allocations', 'Pterodactyl discovery requests only safe optional node/allocation relationships');
    $check($pteroHttp->calls[0]['credential'] === 'application', 'Pterodactyl discovery uses Application credential');
    $check(($pteroHttp->calls[0]['query']['filter']['name'] ?? null) === 'Pterodactyl', 'Pterodactyl discovery search is encoded as a query filter');
    $check($pteroPage->servers[0]['stable_identifier'] === 'efgh5678', 'Pterodactyl stable identifier is Client identifier');
    $check($pteroPage->servers[0]['uuid'] === '223e4567-e89b-42d3-a456-426614174000', 'Pterodactyl UUID retained as safe metadata');
    $check($pteroPage->servers[0]['suspended'] === true, 'Pterodactyl suspended state normalized');
    $check(! $pteroPage->hasMore, 'Pterodactyl final page retained');

    $testHttp = new SafePanelHttpClient();
    $testHttp->queue[] = $response(['data' => [], 'meta' => ['pagination' => ['current_page' => 1, 'total_pages' => 1]]]);
    $testClient = new PelicanClient($testHttp, new PelicanResponseNormalizer(new StateMapper()));
    $testResult = $testClient->testApplication($connection('pelican'));
    $check($testResult->success && $testResult->state === 'application_api', 'Application API connection test succeeds without runtime Client token use');

    $failedHttp = new SafePanelHttpClient();
    $failedHttp->queue[] = new PanelApiException('authentication_failed');
    $failedClient = new PterodactylClient($failedHttp, new PterodactylResponseNormalizer(new StateMapper()));
    $failedResult = $failedClient->testApplication($connection('pterodactyl'));
    $check(! $failedResult->success && $failedResult->errorCategory === 'authentication_failed', 'Application API test failure is safely categorized');

    exit($failures === [] ? 0 : 1);
}

<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;

use Azuriom\Plugin\GamingHubPanel\Clients\PterodactylClient;
use Azuriom\Plugin\GamingHubPanel\Data\PanelConnection;
use Azuriom\Plugin\GamingHubPanel\Data\PanelHttpResponse;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Http\SafePanelHttpClient;
use Azuriom\Plugin\GamingHubPanel\Normalization\PterodactylResponseNormalizer;
use Azuriom\Plugin\GamingHubPanel\Normalization\StateMapper;
use PHPUnit\Framework\TestCase;

final class PterodactylClientTest extends TestCase
{
    private function connection(): PanelConnection
    {
        return new PanelConnection(
            providerId: 2,
            connectionId: 10,
            panelType: 'pterodactyl',
            baseUrl: 'https://panel.test',
            serverIdentifier: 'abcd1234',
            encryptedApplicationToken: 'application-cipher',
            encryptedClientToken: 'client-cipher',
            attributionLabel: 'Pterodactyl',
            timeout: 8,
            verifySsl: true,
            cacheTtl: 15,
        );
    }

    private function response(array $payload): PanelHttpResponse
    {
        return new PanelHttpResponse(200, [], json_encode($payload, JSON_THROW_ON_ERROR), 1);
    }

    private function client(SafePanelHttpClient $http): PterodactylClient
    {
        return new PterodactylClient($http, new PterodactylResponseNormalizer(new StateMapper()));
    }

    public function testStoppedServerMetricsUseMockedResponses(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            $this->response(['attributes' => [
                'identifier' => 'abcd1234',
                'name' => 'Ptero Test',
                'limits' => ['memory' => 0],
                'status' => null,
            ]]),
            $this->response(['attributes' => [
                'current_state' => 'offline',
                'is_suspended' => false,
                'resources' => [
                    'cpu_absolute' => 0,
                    'memory_bytes' => 0,
                    'disk_bytes' => 10,
                    'uptime' => 0,
                ],
            ]]),
        );

        $snapshot = $this->client($http)->snapshot($this->connection());

        self::assertSame('offline', $snapshot->state);
        self::assertNull($snapshot->memoryLimitBytes);
        self::assertSame(0.0, $snapshot->cpuPercent);
    }

    public function testPaginatedDiscoveryUsesClientIdentifier(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->expects(self::once())->method('get')->willReturn($this->response([
            'data' => [[
                'attributes' => [
                    'identifier' => 'abcd1234',
                    'name' => 'Ptero Test',
                    'status' => null,
                ],
            ]],
            'meta' => ['pagination' => ['current_page' => 1, 'total_pages' => 1]],
        ]));

        $page = $this->client($http)->discover($this->connection(), 1, 25, 'Ptero');

        self::assertFalse($page->hasMore);
        self::assertSame('abcd1234', $page->servers[0]['stable_identifier']);
    }

    public function testMalformedPaginationFails(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willReturn($this->response([
            'data' => [],
            'meta' => ['pagination' => ['current_page' => 'one', 'total_pages' => 1]],
        ]));

        $this->expectException(PanelApiException::class);
        $this->client($http)->discover($this->connection(), 1, 25);
    }

    public function testAuthenticationFailureIsNormalized(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willThrowException(new PanelApiException('authentication_failed'));

        self::assertSame('authentication_failed', $this->client($http)->test($this->connection())->errorCategory);
    }

    public function testMissingServerIsNormalized(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willThrowException(new PanelApiException('unavailable', 'Missing server.', 404));

        self::assertSame('unavailable', $this->client($http)->test($this->connection())->errorCategory);
    }

    public function testTimeoutIsNormalized(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willThrowException(new PanelApiException('timeout'));

        self::assertSame('timeout', $this->client($http)->test($this->connection())->errorCategory);
    }

    public function testRateLimitIsUnavailable(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willThrowException(new PanelApiException('unavailable', 'Rate limited.', 429));

        self::assertSame('unavailable', $this->client($http)->test($this->connection())->errorCategory);
    }
}

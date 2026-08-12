<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;

use Azuriom\Plugin\GamingHubPanel\Clients\PelicanClient;
use Azuriom\Plugin\GamingHubPanel\Data\PanelConnection;
use Azuriom\Plugin\GamingHubPanel\Data\PanelHttpResponse;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Http\SafePanelHttpClient;
use Azuriom\Plugin\GamingHubPanel\Normalization\PelicanResponseNormalizer;
use Azuriom\Plugin\GamingHubPanel\Normalization\StateMapper;
use PHPUnit\Framework\TestCase;

final class PelicanClientTest extends TestCase
{
    private function connection(): PanelConnection
    {
        return new PanelConnection(
            providerId: 1,
            connectionId: 10,
            panelType: 'pelican',
            baseUrl: 'https://panel.test',
            serverIdentifier: '123e4567-e89b-42d3-a456-426614174000',
            encryptedApplicationToken: 'application-cipher',
            encryptedClientToken: 'client-cipher',
            attributionLabel: 'Pelican',
            timeout: 8,
            verifySsl: true,
            cacheTtl: 15,
        );
    }

    private function response(array $payload): PanelHttpResponse
    {
        return new PanelHttpResponse(200, [], json_encode($payload, JSON_THROW_ON_ERROR), 1);
    }

    private function client(SafePanelHttpClient $http): PelicanClient
    {
        return new PelicanClient($http, new PelicanResponseNormalizer(new StateMapper()));
    }

    public function testSuccessfulSnapshotUsesMockedResponses(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->expects(self::exactly(2))->method('get')->willReturnOnConsecutiveCalls(
            $this->response(['attributes' => [
                'uuid' => $this->connection()->serverIdentifier,
                'name' => 'Pelican Test',
                'limits' => ['memory' => 512],
            ]]),
            $this->response(['attributes' => [
                'current_state' => 'running',
                'is_suspended' => false,
                'resources' => [
                    'cpu_absolute' => 25,
                    'memory_bytes' => 100,
                    'disk_bytes' => 200,
                    'uptime' => 3000,
                ],
            ]]),
        );

        $snapshot = $this->client($http)->snapshot($this->connection());

        self::assertSame('online', $snapshot->state);
        self::assertSame('Pelican Test', $snapshot->serverName);
    }

    public function testPaginatedDiscoveryUsesFullUuid(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->expects(self::once())->method('get')->willReturn($this->response([
            'data' => [[
                'attributes' => [
                    'uuid' => $this->connection()->serverIdentifier,
                    'name' => 'Pelican Test',
                    'status' => 'running',
                ],
            ]],
            'meta' => ['pagination' => ['current_page' => 1, 'total_pages' => 2]],
        ]));

        $page = $this->client($http)->discover($this->connection(), 1, 25, 'Pelican');

        self::assertTrue($page->hasMore);
        self::assertSame($this->connection()->serverIdentifier, $page->servers[0]['stable_identifier']);
    }

    public function testMalformedDiscoveryItemFails(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willReturn($this->response(['data' => [['attributes' => ['name' => 'Missing UUID']]]]));

        $this->expectException(PanelApiException::class);
        $this->client($http)->discover($this->connection(), 1, 25);
    }

    public function testAuthenticationFailureIsNormalized(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willThrowException(new PanelApiException('authentication_failed'));

        $result = $this->client($http)->test($this->connection());

        self::assertFalse($result->success);
        self::assertSame('authentication_failed', $result->errorCategory);
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

    public function testMalformedResponseFails(): void
    {
        $http = $this->createMock(SafePanelHttpClient::class);
        $http->method('get')->willReturn($this->response(['unexpected' => true]));

        $this->expectException(PanelApiException::class);
        $this->client($http)->snapshot($this->connection());
    }
}

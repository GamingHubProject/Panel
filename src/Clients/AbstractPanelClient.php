<?php

namespace Azuriom\Plugin\GamingHubPanel\Clients;

use Azuriom\Plugin\GamingHubPanel\Contracts\PanelAdapter;
use Azuriom\Plugin\GamingHubPanel\Data\{ConnectionTestResult, PanelHttpResponse, PanelSnapshot};
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Http\SafePanelHttpClient;

abstract class AbstractPanelClient implements PanelAdapter
{
    public function __construct(protected SafePanelHttpClient $http)
    {
    }

    protected function payload(PanelHttpResponse $response): array
    {
        try {
            return $response->json();
        } catch (\JsonException) {
            throw new PanelApiException('invalid_response', 'Panel returned malformed JSON.');
        }
    }

    protected function version(PanelHttpResponse $response, array $headers): ?string
    {
        foreach ($headers as $header) {
            $value = $response->header($header);
            if (is_string($value) && preg_match('/^[0-9A-Za-z._+-]{1,100}$/', $value)) {
                return $value;
            }
        }

        return null;
    }

    protected function safeRuntimeTest(callable $callback, string $type): ConnectionTestResult
    {
        $start = microtime(true);

        try {
            $snapshot = $callback();
            if (! $snapshot instanceof PanelSnapshot || blank($snapshot->serverName)) {
                throw new PanelApiException('invalid_response', 'Panel server details did not include a supported server name.');
            }

            return new ConnectionTestResult(
                true,
                $type,
                $snapshot->detectedVersion,
                $snapshot->serverName,
                $snapshot->state,
                (int) round((microtime(true) - $start) * 1000),
            );
        } catch (PanelApiException $exception) {
            return $this->failedTest($type, $exception->category, $start);
        } catch (\Throwable) {
            return $this->failedTest($type, 'unknown_error', $start);
        }
    }

    protected function safeApplicationTest(callable $callback, string $type): ConnectionTestResult
    {
        $start = microtime(true);

        try {
            $page = $callback();
            $count = is_object($page) && property_exists($page, 'servers') && is_array($page->servers)
                ? count($page->servers)
                : 0;

            return new ConnectionTestResult(
                true,
                $type,
                null,
                $count.' server'.($count === 1 ? '' : 's').' visible on the first page',
                'application_api',
                (int) round((microtime(true) - $start) * 1000),
            );
        } catch (PanelApiException $exception) {
            return $this->failedTest($type, $exception->category, $start);
        } catch (\Throwable) {
            return $this->failedTest($type, 'unknown_error', $start);
        }
    }

    protected function nodeName(array $item, array $attributes): ?string
    {
        $candidates = [
            $attributes['node_name'] ?? null,
            $item['relationships']['node']['data']['attributes']['name'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && strlen($candidate) <= 255) {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function primaryAllocation(array $item): ?string
    {
        $allocations = $item['relationships']['allocations']['data'] ?? null;
        if (! is_array($allocations) || ! array_is_list($allocations)) {
            return null;
        }

        $selected = null;
        foreach ($allocations as $allocation) {
            $attributes = is_array($allocation) ? ($allocation['attributes'] ?? null) : null;
            if (! is_array($attributes)) {
                continue;
            }
            if (($attributes['is_default'] ?? false) === true) {
                $selected = $attributes;
                break;
            }
            $selected ??= $attributes;
        }

        if (! is_array($selected)) {
            return null;
        }

        $host = $selected['alias'] ?? $selected['ip'] ?? null;
        $port = $selected['port'] ?? null;
        if (! is_string($host) || trim($host) === '' || strlen($host) > 200 || ! is_int($port) || $port < 1 || $port > 65535) {
            return null;
        }

        return trim($host).':'.$port;
    }

    private function failedTest(string $type, string $category, float $startedAt): ConnectionTestResult
    {
        return new ConnectionTestResult(
            false,
            $type,
            null,
            null,
            null,
            (int) round((microtime(true) - $startedAt) * 1000),
            $category,
        );
    }
}

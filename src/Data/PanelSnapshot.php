<?php
namespace Azuriom\Plugin\GamingHubPanel\Data;

use Carbon\CarbonImmutable;

final readonly class PanelSnapshot
{
    public function __construct(
        public string $state,
        public ?string $displayMessage,
        public ?string $serverName,
        public ?float $cpuPercent,
        public ?int $memoryUsedBytes,
        public ?int $memoryLimitBytes,
        public ?int $diskUsedBytes,
        public ?int $uptimeSeconds,
        public CarbonImmutable $observedAt,
        public ?CarbonImmutable $sourceUpdatedAt = null,
        public ?string $detectedVersion = null,
        public ?string $resolvedIdentifier = null,
    ) {}

    /** @return array<string, bool|float|int|string|null> */
    public function toCacheArray(): array
    {
        return [
            'state' => $this->state,
            'display_message' => $this->displayMessage,
            'server_name' => $this->serverName,
            'cpu_percent' => $this->cpuPercent,
            'memory_used_bytes' => $this->memoryUsedBytes,
            'memory_limit_bytes' => $this->memoryLimitBytes,
            'disk_used_bytes' => $this->diskUsedBytes,
            'uptime_seconds' => $this->uptimeSeconds,
            'observed_at' => $this->observedAt->toIso8601String(),
            'source_updated_at' => $this->sourceUpdatedAt?->toIso8601String(),
            'detected_version' => $this->detectedVersion,
            'resolved_identifier' => $this->resolvedIdentifier,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromCacheArray(array $payload): ?self
    {
        try {
            if (! is_string($payload['state'] ?? null)
                || ! in_array($payload['state'], ['online', 'offline', 'maintenance', 'unknown'], true)
                || ! is_string($payload['observed_at'] ?? null)) {
                return null;
            }

            return new self(
                state: $payload['state'],
                displayMessage: self::nullableString($payload['display_message'] ?? null),
                serverName: self::nullableString($payload['server_name'] ?? null),
                cpuPercent: self::nullableFloat($payload['cpu_percent'] ?? null),
                memoryUsedBytes: self::nullableInt($payload['memory_used_bytes'] ?? null),
                memoryLimitBytes: self::nullableInt($payload['memory_limit_bytes'] ?? null),
                diskUsedBytes: self::nullableInt($payload['disk_used_bytes'] ?? null),
                uptimeSeconds: self::nullableInt($payload['uptime_seconds'] ?? null),
                observedAt: CarbonImmutable::parse($payload['observed_at']),
                sourceUpdatedAt: is_string($payload['source_updated_at'] ?? null)
                    ? CarbonImmutable::parse($payload['source_updated_at'])
                    : null,
                detectedVersion: self::nullableString($payload['detected_version'] ?? null),
                resolvedIdentifier: self::nullableString($payload['resolved_identifier'] ?? null),
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value !== null && ! is_string($value)) {
            throw new \UnexpectedValueException('Invalid cached string value.');
        }

        return $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new \UnexpectedValueException('Invalid cached float value.');
        }

        return (float) $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value !== null && (! is_int($value) || $value < 0)) {
            throw new \UnexpectedValueException('Invalid cached integer value.');
        }

        return $value;
    }
}

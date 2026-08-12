<?php
namespace Azuriom\Plugin\GamingHubPanel\Normalization;

use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;

abstract class AbstractResponseNormalizer
{
    protected function attributes(array $payload): array
    {
        $attributes = $payload['attributes'] ?? null;
        if (! is_array($attributes) || array_is_list($attributes)) {
            throw new PanelApiException('invalid_response', 'Panel response attributes are missing.');
        }

        return $attributes;
    }

    protected function requiredText(mixed $value, string $field, int $maxLength = 255): string
    {
        $text = $this->nullableText($value, $field, $maxLength);
        if ($text === null) {
            throw new PanelApiException('invalid_response', "Panel response {$field} is missing.");
        }

        return $text;
    }

    protected function nullableText(mixed $value, string $field, int $maxLength = 255): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new PanelApiException('invalid_response', "Panel response {$field} is invalid.");
        }

        $text = trim($value);
        if ($text === '' || strlen($text) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $text)) {
            throw new PanelApiException('invalid_response', "Panel response {$field} is invalid.");
        }

        return $text;
    }

    protected function boolean(mixed $value, string $field, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }
        if (! is_bool($value)) {
            throw new PanelApiException('invalid_response', "Panel response {$field} is invalid.");
        }

        return $value;
    }

    protected function optionalMap(mixed $value, string $field): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value) || array_is_list($value)) {
            throw new PanelApiException('invalid_response', "Panel response {$field} is invalid.");
        }

        return $value;
    }

    protected function nonNegativeInt(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $integer = $value;
        } elseif (is_float($value)) {
            if (! is_finite($value) || floor($value) !== $value || $value > PHP_INT_MAX) {
                throw new PanelApiException('invalid_response', "Invalid {$field} metric.");
            }
            $integer = (int) $value;
        } elseif (is_string($value) && preg_match('/^\d+$/D', $value)) {
            $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($validated === false) {
                throw new PanelApiException('invalid_response', "Invalid {$field} metric.");
            }
            $integer = $validated;
        } else {
            throw new PanelApiException('invalid_response', "Invalid {$field} metric.");
        }

        if ($integer < 0) {
            throw new PanelApiException('invalid_response', "Negative {$field} metric.");
        }

        return $integer;
    }

    protected function nonNegativeFloat(mixed $value, string $field): ?float
    {
        if ($value === null) {
            return null;
        }
        if (! is_numeric($value)) {
            throw new PanelApiException('invalid_response', "Invalid {$field} metric.");
        }

        $float = (float) $value;
        if (! is_finite($float) || $float < 0) {
            throw new PanelApiException('invalid_response', "Negative {$field} metric.");
        }

        return $float;
    }

    protected function mibToBytes(mixed $value): ?int
    {
        $mib = $this->nonNegativeInt($value, 'memory limit');
        if ($mib === null || $mib === 0) {
            return null;
        }
        if ($mib > intdiv(PHP_INT_MAX, 1024 * 1024)) {
            throw new PanelApiException('invalid_response', 'Memory limit metric is too large.');
        }

        return $mib * 1024 * 1024;
    }

    protected function uptimeSeconds(mixed $value): ?int
    {
        $milliseconds = $this->nonNegativeInt($value, 'uptime');

        return $milliseconds === null ? null : intdiv($milliseconds, 1000);
    }
}

<?php
namespace Azuriom\Plugin\GamingHubPanel\Data;
final readonly class PanelHttpResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(public int $status, public array $headers, public string $body, public int $latencyMs) {}
    public function json(): array
    {
        $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) throw new \JsonException('Panel response is not a JSON object.');
        return $decoded;
    }
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) if (strcasecmp($key, $name) === 0) return $values[0] ?? null;
        return null;
    }
}

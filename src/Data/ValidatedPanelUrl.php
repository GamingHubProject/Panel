<?php
namespace Azuriom\Plugin\GamingHubPanel\Data;
final readonly class ValidatedPanelUrl
{
    /** @param list<string> $addresses */
    public function __construct(public string $url, public string $scheme, public string $host, public int $port, public array $addresses) {}
}

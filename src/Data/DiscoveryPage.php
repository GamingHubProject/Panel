<?php

namespace Azuriom\Plugin\GamingHubPanel\Data;

final readonly class DiscoveryPage
{
    /**
     * @param list<array{
     *   stable_identifier:string,
     *   name:string,
     *   uuid:?string,
     *   short_identifier:?string,
     *   node_name:?string,
     *   suspended:?bool,
     *   primary_allocation:?string,
     *   metadata:array<string, scalar|null>
     * }> $servers
     */
    public function __construct(
        public array $servers,
        public int $page,
        public bool $hasMore,
    ) {
    }
}

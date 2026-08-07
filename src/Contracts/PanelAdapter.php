<?php

namespace Azuriom\Plugin\GamingHubPanel\Contracts;

use Azuriom\Plugin\GamingHubPanel\Data\{ConnectionTestResult, DiscoveryPage, PanelConnection, PanelSnapshot};

interface PanelAdapter
{
    public function snapshot(PanelConnection $connection): PanelSnapshot;

    public function discover(PanelConnection $connection, int $page, int $perPage, ?string $search = null): DiscoveryPage;

    public function test(PanelConnection $connection): ConnectionTestResult;

    public function testApplication(PanelConnection $connection): ConnectionTestResult;
}

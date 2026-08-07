<?php
namespace Azuriom\Plugin\GamingHubPanel\Contracts;
interface HostResolver { /** @return list<string> */ public function resolve(string $host): array; }

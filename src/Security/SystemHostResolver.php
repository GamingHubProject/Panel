<?php
namespace Azuriom\Plugin\GamingHubPanel\Security;

use Azuriom\Plugin\GamingHubPanel\Contracts\HostResolver;

final class SystemHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $addresses[] = $ip;
                }
            }
        }

        // gethostbynamel follows the system resolver, including /etc/hosts,
        // which is common for administrator-trusted LAN panel names.
        $ipv4 = gethostbynamel($host);
        if (is_array($ipv4)) {
            foreach ($ipv4 as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $addresses[] = $ip;
                }
            }
        }

        return array_values(array_unique($addresses));
    }
}

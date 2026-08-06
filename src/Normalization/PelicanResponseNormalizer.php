<?php
namespace Azuriom\Plugin\GamingHubPanel\Normalization;

use Azuriom\Plugin\GamingHubPanel\Data\PanelSnapshot;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Carbon\CarbonImmutable;

final class PelicanResponseNormalizer extends AbstractResponseNormalizer
{
    public function __construct(private StateMapper $states) {}

    public function snapshot(array $serverPayload, array $resourcePayload, ?string $version = null): PanelSnapshot
    {
        $server = $this->attributes($serverPayload);
        $runtime = $this->attributes($resourcePayload);
        $resources = $runtime['resources'] ?? null;
        if (! is_array($resources) || array_is_list($resources)) {
            throw new PanelApiException('invalid_response', 'Pelican resource data is missing.');
        }

        $rawState = $this->requiredText($runtime['current_state'] ?? null, 'current state', 32);
        $suspended = $this->boolean($runtime['is_suspended'] ?? null, 'suspension flag');
        $installing = $this->boolean($server['is_installing'] ?? null, 'installation flag');
        $transferring = $this->boolean($server['is_transferring'] ?? null, 'transfer flag');
        $limits = $this->optionalMap($server['limits'] ?? null, 'limits');
        [$state, $message] = $this->states->map($rawState, $suspended, $installing || $transferring);

        $uuid = $this->requiredText($server['uuid'] ?? null, 'server UUID', 36);
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            throw new PanelApiException('invalid_response', 'Pelican response server UUID is invalid.');
        }

        return new PanelSnapshot(
            $state,
            $message,
            $this->requiredText($server['name'] ?? null, 'server name'),
            $this->nonNegativeFloat($resources['cpu_absolute'] ?? null, 'CPU'),
            $this->nonNegativeInt($resources['memory_bytes'] ?? null, 'memory'),
            $this->mibToBytes($limits['memory'] ?? null),
            $this->nonNegativeInt($resources['disk_bytes'] ?? null, 'disk'),
            $this->uptimeSeconds($resources['uptime'] ?? null),
            CarbonImmutable::now(),
            null,
            $version,
            strtolower($uuid),
        );
    }
}

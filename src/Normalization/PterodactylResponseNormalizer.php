<?php
namespace Azuriom\Plugin\GamingHubPanel\Normalization;

use Azuriom\Plugin\GamingHubPanel\Data\PanelSnapshot;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Carbon\CarbonImmutable;

final class PterodactylResponseNormalizer extends AbstractResponseNormalizer
{
    public function __construct(private StateMapper $states) {}

    public function snapshot(array $serverPayload, array $resourcePayload, ?string $version = null): PanelSnapshot
    {
        $server = $this->attributes($serverPayload);
        $runtime = $this->attributes($resourcePayload);
        $resources = $runtime['resources'] ?? null;
        if (! is_array($resources) || array_is_list($resources)) {
            throw new PanelApiException('invalid_response', 'Pterodactyl resource data is missing.');
        }

        $rawState = $this->requiredText($runtime['current_state'] ?? null, 'current state', 32);
        $suspended = $this->boolean($runtime['is_suspended'] ?? null, 'suspension flag');
        $panelStatus = $this->nullableText($server['status'] ?? null, 'server status', 32);
        $limits = $this->optionalMap($server['limits'] ?? null, 'limits');
        $maintenance = in_array((string) $panelStatus, [
            'installing',
            'install_failed',
            'suspended',
            'restoring',
            'restoring_backup',
            'transferring',
            'transfer_failed',
        ], true);
        [$state, $message] = $this->states->map($rawState, $suspended, $maintenance);

        $identifier = $this->requiredText($server['identifier'] ?? null, 'server identifier', 64);
        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $identifier)) {
            throw new PanelApiException('invalid_response', 'Pterodactyl response server identifier is invalid.');
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
            $identifier,
        );
    }
}

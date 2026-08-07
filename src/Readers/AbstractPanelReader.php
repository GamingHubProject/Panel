<?php
namespace Azuriom\Plugin\GamingHubPanel\Readers;
use Azuriom\Plugin\GamingHubCore\Contracts\CapabilityReader;
use Azuriom\Plugin\GamingHubCore\Data\{ProviderInstanceData,SharedDataResult};
use Azuriom\Plugin\GamingHubCore\Models\Server;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
use Azuriom\Plugin\GamingHubPanel\Services\{ProviderConfiguration,PanelSnapshotService};
abstract class AbstractPanelReader implements CapabilityReader
{
    public function __construct(protected ProviderConfiguration $configuration,protected PanelSnapshotService $snapshots){}
    final public function read(Server $server,ProviderInstanceData $provider):SharedDataResult
    {
        try{$connection=$this->configuration->fromProvider($provider);if($connection->panelType!==$this->panelType())return SharedDataResult::failed($this->capability(),(int)$server->id,'configuration_invalid','Reader/provider type mismatch.');$snapshot=$this->snapshots->get($connection);return$this->result($server,$provider,$snapshot,$connection->attributionLabel);}
        catch(PanelApiException $e){return SharedDataResult::failed($this->capability(),(int)$server->id,$e->category,$e->getMessage());}
    }
    public function cacheTtl():int{return 1;}
    abstract protected function panelType():string; abstract protected function capability():string;
    abstract protected function result(Server $server,ProviderInstanceData $provider,\Azuriom\Plugin\GamingHubPanel\Data\PanelSnapshot $snapshot,string $sourceLabel):SharedDataResult;
}

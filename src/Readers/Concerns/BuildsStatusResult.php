<?php
namespace Azuriom\Plugin\GamingHubPanel\Readers\Concerns;
use Azuriom\Plugin\GamingHubCore\Data\{ProviderInstanceData,ServerStatusData,SharedDataResult};
use Azuriom\Plugin\GamingHubCore\Models\Server;
use Azuriom\Plugin\GamingHubPanel\Data\PanelSnapshot;
trait BuildsStatusResult
{
    protected function capability():string{return'server-status';}
    protected function result(Server $server,ProviderInstanceData $provider,PanelSnapshot $s,string $sourceLabel):SharedDataResult
    {$dto=new ServerStatusData($s->state,$s->displayMessage,null,$s->serverName,null,null,$s->uptimeSeconds,$s->observedAt,$s->sourceUpdatedAt);return new SharedDataResult('server-status',(int)$server->id,'available',$dto->toArray(),$s->observedAt,$s->sourceUpdatedAt,$provider->providerType,$provider->id,null,null,$sourceLabel);}
}

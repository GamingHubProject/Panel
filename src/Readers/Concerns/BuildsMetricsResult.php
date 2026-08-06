<?php
namespace Azuriom\Plugin\GamingHubPanel\Readers\Concerns;
use Azuriom\Plugin\GamingHubCore\Data\{ProviderInstanceData,MetricsData,SharedDataResult};
use Azuriom\Plugin\GamingHubCore\Models\Server;
use Azuriom\Plugin\GamingHubPanel\Data\PanelSnapshot;
trait BuildsMetricsResult
{
    protected function capability():string{return'metrics';}
    protected function result(Server $server,ProviderInstanceData $provider,PanelSnapshot $s,string $sourceLabel):SharedDataResult
    {$dto=new MetricsData($s->cpuPercent,$s->memoryUsedBytes,$s->memoryLimitBytes,$s->diskUsedBytes,$s->observedAt,$s->sourceUpdatedAt);return new SharedDataResult('metrics',(int)$server->id,'available',$dto->toArray(),$s->observedAt,$s->sourceUpdatedAt,$provider->providerType,$provider->id,null,null,$sourceLabel);}
}

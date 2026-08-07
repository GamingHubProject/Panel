<?php
namespace Azuriom\Plugin\GamingHubPanel\Services;
use Azuriom\Plugin\GamingHubPanel\Clients\{PelicanClient,PterodactylClient};
use Azuriom\Plugin\GamingHubPanel\Contracts\PanelAdapter;
use Azuriom\Plugin\GamingHubPanel\Exceptions\PanelApiException;
final class PanelClientFactory
{
    public function __construct(private PelicanClient $pelican,private PterodactylClient $pterodactyl){}
    public function for(string $type):PanelAdapter{return match($type){'pelican'=>$this->pelican,'pterodactyl'=>$this->pterodactyl,default=>throw new PanelApiException('unsupported','Unsupported panel provider type.')};}
}

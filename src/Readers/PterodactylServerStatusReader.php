<?php
namespace Azuriom\Plugin\GamingHubPanel\Readers;
use Azuriom\Plugin\GamingHubPanel\Readers\Concerns\BuildsStatusResult;
final class PterodactylServerStatusReader extends AbstractPanelReader { use BuildsStatusResult; protected function panelType():string{return'pterodactyl';} }

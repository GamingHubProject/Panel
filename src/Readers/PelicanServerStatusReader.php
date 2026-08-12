<?php
namespace Azuriom\Plugin\GamingHubPanel\Readers;
use Azuriom\Plugin\GamingHubPanel\Readers\Concerns\BuildsStatusResult;
final class PelicanServerStatusReader extends AbstractPanelReader { use BuildsStatusResult; protected function panelType():string{return'pelican';} }

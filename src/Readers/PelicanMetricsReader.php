<?php
namespace Azuriom\Plugin\GamingHubPanel\Readers;
use Azuriom\Plugin\GamingHubPanel\Readers\Concerns\BuildsMetricsResult;
final class PelicanMetricsReader extends AbstractPanelReader { use BuildsMetricsResult; protected function panelType():string{return'pelican';} }

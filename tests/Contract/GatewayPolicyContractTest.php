<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;
use PHPUnit\Framework\TestCase;
final class GatewayPolicyContractTest extends TestCase
{
 public function testReadersReturnOnlyCoreDtos():void{foreach(['BuildsStatusResult.php'=>'ServerStatusData','BuildsMetricsResult.php'=>'MetricsData'] as $file=>$dto){$s=file_get_contents(dirname(__DIR__,2).'/src/Readers/Concerns/'.$file);self::assertStringContainsString($dto,$s);self::assertStringContainsString('SharedDataResult',$s);}}
 public function testPublicViewUsesCorePublicRead():void{$s=file_get_contents(dirname(__DIR__,2).'/resources/views/core-runtime/servers/show-v044.blade.php');self::assertStringContainsString("->publicRead(\$ghPanelServerModel, 'metrics')",$s);self::assertStringContainsString("->publicRead(\$ghPanelServerModel, 'server-status')",$s);self::assertStringNotContainsString('panel_url',$s);self::assertStringNotContainsString('panel_server_identifier',$s);}
 public function testNoPlayerCountsAreInvented():void{$s=file_get_contents(dirname(__DIR__,2).'/src/Readers/Concerns/BuildsStatusResult.php');self::assertStringContainsString('null,null,$s->uptimeSeconds',$s);}
}

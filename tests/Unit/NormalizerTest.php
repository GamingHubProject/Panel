<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;
use Azuriom\Plugin\GamingHubPanel\Normalization\{StateMapper,PelicanResponseNormalizer,PterodactylResponseNormalizer};
use PHPUnit\Framework\TestCase;
final class NormalizerTest extends TestCase
{
 private function server():array{return['attributes'=>['uuid'=>'123e4567-e89b-42d3-a456-426614174000','identifier'=>'abcd1234','name'=>'Test','limits'=>['memory'=>1024],'status'=>null]];}
 private function resources():array{return['attributes'=>['current_state'=>'running','is_suspended'=>false,'resources'=>['cpu_absolute'=>125.5,'memory_bytes'=>1000,'disk_bytes'=>2000,'uptime'=>15000]]];}
 public function testPelican():void{$s=(new PelicanResponseNormalizer(new StateMapper()))->snapshot($this->server(),$this->resources());self::assertSame('online',$s->state);self::assertSame(1073741824,$s->memoryLimitBytes);self::assertSame(15,$s->uptimeSeconds);}
 public function testPterodactyl():void{$s=(new PterodactylResponseNormalizer(new StateMapper()))->snapshot($this->server(),$this->resources());self::assertSame(125.5,$s->cpuPercent);self::assertSame(2000,$s->diskUsedBytes);}
}

<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;
use Azuriom\Plugin\GamingHubPanel\Normalization\StateMapper;
use PHPUnit\Framework\TestCase;
final class StateMapperTest extends TestCase{public function testMappings():void{$m=new StateMapper();self::assertSame('online',$m->map('running')[0]);self::assertSame('offline',$m->map('offline')[0]);self::assertSame('maintenance',$m->map('starting')[0]);self::assertSame('unknown',$m->map('surprise')[0]);self::assertSame('maintenance',$m->map('running',true)[0]);}}

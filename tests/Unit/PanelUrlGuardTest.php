<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Unit;
use Azuriom\Plugin\GamingHubPanel\Contracts\HostResolver;
use Azuriom\Plugin\GamingHubPanel\Exceptions\UnsafePanelUrl;
use Azuriom\Plugin\GamingHubPanel\Security\PanelUrlGuard;
use Azuriom\Plugin\GamingHubPanel\Settings\PanelSettings;
use PHPUnit\Framework\TestCase;
final class PanelUrlGuardTest extends TestCase
{
 private function guard(array $ips,bool $private=false,bool $http=false):PanelUrlGuard{$resolver=new class($ips) implements HostResolver{public function __construct(private array $ips){}public function resolve(string $host):array{return$this->ips;}};$settings=$this->createMock(PanelSettings::class);$settings->method('all')->willReturn(['allow_private_hosts'=>$private,'allow_insecure_http'=>$http]);return new PanelUrlGuard($resolver,$settings);}
 public function testPublicHttpsAccepted():void{self::assertSame('panel.example',$this->guard(['93.184.216.34'])->validate('https://panel.example')->host);}
 public function testCredentialsRejected():void{$this->expectException(UnsafePanelUrl::class);$this->guard(['93.184.216.34'])->validate('https://user:pass@panel.example');}
 public function testPrivateHostRequiresTrust():void{$this->expectException(UnsafePanelUrl::class);$this->guard(['192.168.1.2'])->validate('https://panel.lan');}
 public function testCrossHostRedirectRejected():void{$g=$this->guard(['93.184.216.34']);$origin=$g->validate('https://panel.example');$this->expectException(UnsafePanelUrl::class);$g->validateRedirect($origin,'https://evil.example/api');}
}

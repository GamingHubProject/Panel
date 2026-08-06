<?php
namespace Azuriom\Plugin\GamingHubPanel\Tests\Contract;
use PHPUnit\Framework\TestCase;
final class CacheErrorRegressionContractTest extends TestCase
{
 public function testSnapshotCacheAndInvalidation():void{$s=file_get_contents(dirname(__DIR__,2).'/src/Services/PanelSnapshotService.php');self::assertStringContainsString('gaming-hub-panel:snapshot:',$s);self::assertStringContainsString('max(5,min(300',$s);$provider=file_get_contents(dirname(__DIR__,2).'/src/Providers/GamingHubPanelServiceProvider.php');self::assertStringContainsString('ProviderInstance::saved',$provider);self::assertStringContainsString('ProviderInstance::deleted',$provider);}
 public function testSafeErrorCategories():void{$s=file_get_contents(dirname(__DIR__,2).'/src/Http/SafePanelHttpClient.php');foreach(['authentication_failed','connection_failed','timeout','invalid_response','configuration_invalid','unavailable','unknown_error'] as $category)self::assertStringContainsString($category,$s);}
 public function testManualProviderStillUsesGenericFields():void{$form=file_get_contents(dirname(__DIR__,2).'/resources/views/core-overrides/admin/providers/_form.blade.php');self::assertStringContainsString('@foreach($type->fields as $field)',$form);$controller=file_get_contents(dirname(__DIR__,2).'/src/Controllers/Admin/PanelProviderController.php');self::assertStringContainsString('$this->validator->validate',$controller);}
 public function testPublicViewCatchesFailures():void{$s=file_get_contents(dirname(__DIR__,2).'/resources/views/core-runtime/servers/show-v044.blade.php');self::assertStringContainsString('catch (\\Throwable)',$s);}
}

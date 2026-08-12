<?php
namespace Azuriom\Plugin\GamingHubPanel\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class SaveSettingsRequest extends FormRequest
{
 public function authorize():bool{return$this->user()?->can('gaminghub-panel.settings.manage')===true;}
 public function rules():array{return['default_timeout'=>['required','integer','min:2','max:30'],'default_ttl'=>['required','integer','min:5','max:300'],'default_tls_verify'=>['required','boolean'],'allow_private_hosts'=>['required','boolean'],'allow_insecure_http'=>['required','boolean'],'prerelease_warnings'=>['required','boolean']];}
 protected function prepareForValidation():void{foreach(['default_tls_verify','allow_private_hosts','allow_insecure_http','prerelease_warnings'] as $k)$this->merge([$k=>$this->boolean($k)]);}
}

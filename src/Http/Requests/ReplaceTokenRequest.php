<?php
namespace Azuriom\Plugin\GamingHubPanel\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class ReplaceTokenRequest extends FormRequest
{
    protected $dontFlash = ['token'];
    public function authorize(): bool { return $this->user()?->can('gaminghub-panel.providers.configure') === true && $this->user()?->can('gaminghub.providers.manage') === true; }
    public function rules(): array { return ['token' => ['required','string','max:4096']]; }
}

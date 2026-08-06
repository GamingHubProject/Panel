<?php

namespace Azuriom\Plugin\GamingHubPanel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReplaceConnectionTokenRequest extends FormRequest
{
    protected $dontFlash = ['token'];

    public function authorize(): bool
    {
        return $this->user()?->can('gaminghub-panel.connections.manage') === true;
    }

    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:4096']];
    }
}

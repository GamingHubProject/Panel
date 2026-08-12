<?php

namespace Azuriom\Plugin\GamingHubPanel\Http\Requests;

use Azuriom\Plugin\GamingHubPanel\Exceptions\UnsafePanelUrl;
use Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile;
use Azuriom\Plugin\GamingHubPanel\Security\PanelUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SavePanelConnectionRequest extends FormRequest
{
    protected $dontFlash = ['application_token', 'default_client_token'];

    private ?string $normalizedBaseUrl = null;

    public function authorize(): bool
    {
        return $this->user()?->can('gaminghub-panel.connections.manage') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'panel_type' => ['required', Rule::in(['pelican', 'pterodactyl'])],
            'base_url' => ['required', 'url', 'max:2048'],
            'application_token' => ['nullable', 'string', 'max:4096'],
            'default_client_token' => ['nullable', 'string', 'max:4096'],
            'timeout' => ['required', 'integer', 'min:2', 'max:30'],
            'cache_ttl' => ['required', 'integer', 'min:5', 'max:300'],
            'verify_tls' => ['required', 'boolean'],
            'enabled' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'verify_tls' => $this->boolean('verify_tls'),
            'enabled' => $this->boolean('enabled'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            try {
                $this->normalizedBaseUrl = app(PanelUrlGuard::class)
                    ->validate((string) $this->input('base_url'))
                    ->url;
            } catch (UnsafePanelUrl $exception) {
                $validator->errors()->add('base_url', $exception->getMessage());
            } catch (\Throwable) {
                $validator->errors()->add('base_url', 'Panel URL could not be validated safely.');
            }

            $connection = $this->route('connection');
            if (! $connection instanceof PanelConnectionProfile
                && blank($this->input('application_token'))) {
                $validator->errors()->add('application_token', 'An Application API key is required when creating a connection.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function connectionData(): array
    {
        $data = $this->validated();
        unset($data['application_token'], $data['default_client_token']);
        $data['base_url'] = $this->normalizedBaseUrl ?? (string) $data['base_url'];

        return $data;
    }
}

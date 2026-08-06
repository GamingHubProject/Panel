<?php

namespace Azuriom\Plugin\GamingHubPanel\Http\Requests;

use Azuriom\Plugin\GamingHubCore\Contracts\ProviderTypeRegistry;
use Azuriom\Plugin\GamingHubPanel\Models\{DiscoveredPanelServer, PanelConnectionProfile};
use Azuriom\Plugin\GamingHubPanel\Services\PanelCredentialStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SavePanelProviderRequest extends FormRequest
{
    protected $dontFlash = ['client_token_override'];

    private ?DiscoveredPanelServer $validatedServer = null;
    private ?PanelConnectionProfile $validatedConnection = null;
    private bool $preserveLegacy = false;

    public function authorize(): bool
    {
        if ($this->user()?->can('gaminghub.providers.manage') !== true) {
            return false;
        }

        $type = (string) $this->input('provider_type', $this->route('provider')?->provider_type ?? '');

        return ! in_array($type, ['pelican', 'pterodactyl'], true)
            || ($this->user()?->can('gaminghub-panel.providers.configure') === true
                && $this->user()?->can('gaminghub-panel.connections.view') === true);
    }

    public function rules(): array
    {
        $ids = array_map(
            static fn ($type) => $type->id,
            array_filter(app(ProviderTypeRegistry::class)->all(), static fn ($type) => $type->available),
        );

        return [
            'provider_type' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', Rule::in($ids)],
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:-2147483648', 'max:2147483647'],
            'configuration' => ['required', 'array'],
            'configuration.mapping_mode' => ['nullable', Rule::in(['connection', 'legacy'])],
            'configuration.panel_connection_id' => ['nullable', 'integer', 'min:1'],
            'configuration.discovered_server_id' => ['nullable', 'integer', 'min:1'],
            'configuration.panel_server_identifier' => ['nullable', 'string', 'max:64'],
            'configuration.manual_server_identifier' => ['nullable', 'string', 'max:64'],
            'configuration.manual_identifier' => ['nullable', 'boolean'],
            'configuration.public_attribution_label' => ['nullable', 'string', 'max:100'],
            'configuration.request_timeout' => ['nullable', 'integer', 'min:2', 'max:30'],
            'configuration.cache_ttl' => ['nullable', 'integer', 'min:5', 'max:300'],
            'client_token_override' => ['nullable', 'string', 'max:4096'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['enabled' => $this->boolean('enabled')]);
        $configuration = $this->input('configuration', []);
        if (is_array($configuration)) {
            $configuration['manual_identifier'] = $this->boolean('configuration.manual_identifier');
            $this->merge(['configuration' => $configuration]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('provider_type');
            $configuration = $this->input('configuration', []);
            if (! in_array($type, ['pelican', 'pterodactyl'], true) || ! is_array($configuration)) {
                return;
            }

            $provider = $this->route('provider');
            $legacyExisting = $provider !== null
                && ! array_key_exists('panel_connection_id', (array) $provider->configuration);
            if ($legacyExisting && ($configuration['mapping_mode'] ?? 'legacy') === 'legacy') {
                $this->preserveLegacy = true;
                return;
            }

            $connectionId = filter_var($configuration['panel_connection_id'] ?? null, FILTER_VALIDATE_INT);
            $connection = $connectionId === false ? null : PanelConnectionProfile::query()->find($connectionId);
            if ($connection === null) {
                $validator->errors()->add('configuration.panel_connection_id', 'Select a valid Panel Connection.');
                return;
            }
            $existingConnectionId = $provider === null
                ? 0
                : (int) data_get((array) $provider->configuration, 'panel_connection_id', 0);
            $preservingExistingConnection = $provider !== null
                && $existingConnectionId === (int) $connection->id;
            if (! $connection->enabled && ! $preservingExistingConnection) {
                $validator->errors()->add('configuration.panel_connection_id', 'Disabled Panel Connections cannot be selected for new mappings.');
            }
            if (! hash_equals((string) $connection->panel_type, $type)) {
                $validator->errors()->add('configuration.panel_connection_id', 'The Panel Connection type does not match the provider type.');
            }
            $this->validatedConnection = $connection;

            $existingIdentifier = $provider === null
                ? ''
                : (string) data_get((array) $provider->configuration, 'panel_server_identifier', '');
            $manual = (bool) ($configuration['manual_identifier'] ?? false);
            if ($manual) {
                $identifier = trim((string) ($configuration['manual_server_identifier'] ?? ''));
                $this->validateIdentifier($validator, $type, $identifier);

                if (! $connection->enabled && ! hash_equals($existingIdentifier, $identifier)) {
                    $validator->errors()->add('configuration.manual_server_identifier', 'A disabled Panel Connection can only preserve its existing server mapping.');
                }

                $knownElsewhere = DiscoveredPanelServer::query()
                    ->where('stable_identifier', $identifier)
                    ->where('connection_id', '!=', $connection->id)
                    ->exists();
                if ($knownElsewhere) {
                    $validator->errors()->add('configuration.manual_server_identifier', 'This identifier is known to belong to another Panel Connection.');
                }
            } else {
                $serverId = filter_var($configuration['discovered_server_id'] ?? null, FILTER_VALIDATE_INT);
                $server = $serverId === false ? null : DiscoveredPanelServer::query()->find($serverId);
                $preservingExistingServer = $server !== null
                    && $preservingExistingConnection
                    && hash_equals($existingIdentifier, (string) $server->stable_identifier);
                if ($server === null
                    || (int) $server->connection_id !== (int) $connection->id
                    || (! $server->available && ! $preservingExistingServer)
                    || (! $connection->enabled && ! $preservingExistingServer)) {
                    $validator->errors()->add('configuration.discovered_server_id', 'Select an available discovered server from this Panel Connection.');
                } else {
                    $this->validatedServer = $server;
                }
            }

            $label = (string) ($configuration['public_attribution_label'] ?? '');
            if (preg_match('/[\x00-\x1F\x7F]/', $label)) {
                $validator->errors()->add('configuration.public_attribution_label', 'Attribution label contains unsupported control characters.');
            }

            $presence = $provider === null
                ? ['runtime' => false]
                : app(PanelCredentialStore::class)->presence((int) $provider->id);
            if (blank($this->input('client_token_override'))
                && ! $connection->hasDefaultClientToken()
                && ! $presence['runtime']) {
                $validator->errors()->add('client_token_override', 'This connection has no default Client API token, so a per-server Client token is required.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function providerData(): array
    {
        $data = $this->validated();
        unset($data['client_token_override']);

        $type = (string) $data['provider_type'];
        if (! in_array($type, ['pelican', 'pterodactyl'], true)) {
            return $data;
        }

        if ($this->preserveLegacy) {
            $data['configuration'] = (array) $this->route('provider')->configuration;
            return $data;
        }

        $configuration = $data['configuration'];
        $identifier = (bool) ($configuration['manual_identifier'] ?? false)
            ? trim((string) ($configuration['manual_server_identifier'] ?? ''))
            : (string) $this->validatedServer?->stable_identifier;

        $data['configuration'] = [
            'panel_connection_id' => (int) $this->validatedConnection?->id,
            'panel_server_identifier' => $identifier,
            'manual_identifier' => (bool) ($configuration['manual_identifier'] ?? false),
            'public_attribution_label' => trim((string) ($configuration['public_attribution_label'] ?? '')),
            'request_timeout' => $configuration['request_timeout'] ?? null,
            'cache_ttl' => $configuration['cache_ttl'] ?? null,
        ];

        return $data;
    }

    public function preservesLegacy(): bool
    {
        return $this->preserveLegacy;
    }

    private function validateIdentifier(Validator $validator, string $type, string $identifier): void
    {
        if ($type === 'pelican'
            && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD', $identifier)) {
            $validator->errors()->add('configuration.manual_server_identifier', 'Pelican requires the full server UUID.');
        }
        if ($type === 'pterodactyl' && ! preg_match('/^[A-Za-z0-9-]{8,64}$/D', $identifier)) {
            $validator->errors()->add('configuration.manual_server_identifier', 'Invalid Pterodactyl server identifier.');
        }
    }
}

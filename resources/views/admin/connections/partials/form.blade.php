@csrf
@if($connection) @method('PUT') @endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="name">Display name</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" maxlength="255" value="{{ old('name', $connection?->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="panel_type">Panel type</label>
        <select class="form-select @error('panel_type') is-invalid @enderror" id="panel_type" name="panel_type" required>
            <option value="pelican" @selected(old('panel_type', $connection?->panel_type)==='pelican')>Pelican</option>
            <option value="pterodactyl" @selected(old('panel_type', $connection?->panel_type)==='pterodactyl')>Pterodactyl</option>
        </select>
        @error('panel_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="base_url">Panel base URL</label>
    <input class="form-control @error('base_url') is-invalid @enderror" id="base_url" type="url" name="base_url" maxlength="2048" placeholder="https://panel.example.com" value="{{ old('base_url', $connection?->base_url) }}" required>
    @error('base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="application_token">Application API key {{ $connection ? '(leave blank to preserve)' : '' }}</label>
        <input class="form-control @error('application_token') is-invalid @enderror" id="application_token" type="password" name="application_token" maxlength="4096" autocomplete="new-password" @required(!$connection)>
        <div class="form-text">Used only for administrator-authorized connection testing and server discovery. Never displayed again.</div>
        @error('application_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="default_client_token">Default Client API token {{ $connection ? '(leave blank to preserve)' : '(optional)' }}</label>
        <input class="form-control @error('default_client_token') is-invalid @enderror" id="default_client_token" type="password" name="default_client_token" maxlength="4096" autocomplete="new-password">
        <div class="form-text">Used for runtime status and metrics unless a provider mapping has its own Client-token override.</div>
        @error('default_client_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label" for="timeout">Default timeout</label>
        <input class="form-control" id="timeout" type="number" min="2" max="30" name="timeout" value="{{ old('timeout', $connection?->timeout ?? $defaults['default_timeout']) }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label" for="cache_ttl">Default cache TTL</label>
        <input class="form-control" id="cache_ttl" type="number" min="5" max="300" name="cache_ttl" value="{{ old('cache_ttl', $connection?->cache_ttl ?? $defaults['default_ttl']) }}" required>
    </div>
    <div class="col-md-4 mb-3 pt-4">
        <input type="hidden" name="verify_tls" value="0">
        <div class="form-check">
            <input class="form-check-input" id="verify_tls" type="checkbox" name="verify_tls" value="1" @checked(old('verify_tls', $connection?->verify_tls ?? $defaults['default_tls_verify']))>
            <label class="form-check-label" for="verify_tls">Verify TLS certificate</label>
        </div>
    </div>
</div>

<input type="hidden" name="enabled" value="0">
<div class="form-check mb-3">
    <input class="form-check-input" id="enabled" type="checkbox" name="enabled" value="1" @checked(old('enabled', $connection?->enabled ?? true))>
    <label class="form-check-label" for="enabled">Enabled and selectable for new provider mappings</label>
</div>

<div class="alert alert-info">The Application API key does not replace a Client API token. Runtime status and metrics require a connection default Client token or a per-server override.</div>
<button class="btn btn-primary" type="submit">{{ $connection ? 'Save Connection' : 'Create Connection' }}</button>
<a class="btn btn-secondary" href="{{ route('gaming-hub-panel.admin.connections.index') }}">Cancel</a>

@extends('admin.layouts.admin')
@section('title','Panel Provider Diagnostics')
@section('content')
@include('gaming-hub-panel::admin._boot-diagnostics')
<p><a href="{{ route('gaming-hub-core.admin.games.servers.providers.index',[$game,$server]) }}">Back to providers</a></p>
<div class="card"><div class="card-header">Provider mapping</div><div class="card-body"><dl class="row mb-0">
<dt class="col-sm-4">Panel type</dt><dd class="col-sm-8">{{ $provider->provider_type }}</dd>
<dt class="col-sm-4">Configuration model</dt><dd class="col-sm-8">{{ $legacy ? 'Legacy direct v0.1.x' : 'Global Panel Connection mapping' }}</dd>
<dt class="col-sm-4">Panel Connection</dt><dd class="col-sm-8">{{ $legacy ? 'Not mapped' : ($connection?->name ?? 'Missing connection') }}</dd>
<dt class="col-sm-4">Connection state</dt><dd class="col-sm-8">{{ $legacy ? 'Legacy' : (($connection?->enabled ?? false) ? 'Enabled' : 'Disabled / missing') }}</dd>
<dt class="col-sm-4">URL host</dt><dd class="col-sm-8">{{ $redactedHost }}</dd>
<dt class="col-sm-4">Stored server identifier</dt><dd class="col-sm-8"><code>{{ data_get($provider->configuration,'panel_server_identifier') }}</code></dd>
<dt class="col-sm-4">Connection Application key</dt><dd class="col-sm-8">{{ $tokenPresence['connection_application']?'Configured':'Not available' }}</dd>
<dt class="col-sm-4">Connection default Client token</dt><dd class="col-sm-8">{{ $tokenPresence['connection_default_client']?'Configured':'Not available' }}</dd>
<dt class="col-sm-4">Per-server Client override</dt><dd class="col-sm-8">{{ $tokenPresence['client_override']?'Configured':'Not configured' }}</dd>
@if($legacy)<dt class="col-sm-4">Legacy API token</dt><dd class="col-sm-8">{{ $tokenPresence['legacy_api']?'Configured':'Missing' }}</dd>@endif
<dt class="col-sm-4">Resolved runtime credential</dt><dd class="col-sm-8">{{ $tokenPresence['client_override']||$tokenPresence['connection_default_client']||($legacy&&($tokenPresence['legacy_api']||$tokenPresence['client_override']))?'Available':'configuration_invalid' }}</dd>
<dt class="col-sm-4">Last attempt</dt><dd class="col-sm-8">{{ $diagnostic?->last_attempted_at??'Never' }}</dd>
<dt class="col-sm-4">Last success</dt><dd class="col-sm-8">{{ $diagnostic?->last_successful_at??'Never' }}</dd>
<dt class="col-sm-4">Last safe error</dt><dd class="col-sm-8">{{ $diagnostic?->last_error_category??'None' }}</dd>
<dt class="col-sm-4">Detected version</dt><dd class="col-sm-8">{{ $diagnostic?->detected_version??'Not reported' }}</dd>
<dt class="col-sm-4">Last runtime state</dt><dd class="col-sm-8">{{ $diagnostic?->last_state??'Unknown' }}</dd>
<dt class="col-sm-4">Cache</dt><dd class="col-sm-8">{{ $cacheState }}</dd>
<dt class="col-sm-4">Capabilities</dt><dd class="col-sm-8">server-status, metrics</dd>
</dl></div></div>

<div class="card mt-3"><div class="card-header">Effective public visibility</div><div class="card-body"><table class="table"><thead><tr><th>Statistic</th><th>Visible</th><th>Attribution</th></tr></thead><tbody>@foreach($visibility as $key=>$policy)<tr><td><code>{{ $key }}</code></td><td>{{ $policy['visible']?'Yes':'No' }}</td><td>{{ $policy['attribution']?'Yes':'No' }}</td></tr>@endforeach</tbody></table></div></div>
<div class="mt-3">@can('gaminghub-panel.connections.test')<form class="d-inline" method="POST" action="{{ route('gaming-hub-panel.admin.providers.test',[$game,$server,$provider]) }}">@csrf<button class="btn btn-primary">Test Runtime Connection</button></form>@endcan</div>

@can('gaminghub.providers.manage')@can('gaminghub-panel.providers.configure')
<div class="card mt-3"><div class="card-header">{{ $legacy ? 'Legacy direct credentials' : 'Per-server Client token override' }}</div><div class="card-body">
@if($legacy)
<form class="mb-3" method="POST" action="{{ route('gaming-hub-panel.admin.providers.credentials.replace',[$game,$server,$provider,'api']) }}">@csrf @method('PUT')<label class="form-label">Replace legacy API token</label><div class="input-group"><input class="form-control" type="password" name="token" required maxlength="4096" autocomplete="new-password"><button class="btn btn-outline-primary">Replace</button></div></form>
<form class="mb-3" method="POST" action="{{ route('gaming-hub-panel.admin.providers.credentials.remove',[$game,$server,$provider,'api']) }}" onsubmit="return confirm('Remove this legacy API token?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove legacy API token</button></form>
@endif
<form method="POST" action="{{ route('gaming-hub-panel.admin.providers.credentials.replace',[$game,$server,$provider,'runtime']) }}">@csrf @method('PUT')<label class="form-label">{{ $legacy ? 'Replace legacy runtime Client token' : 'Replace Client API token override' }}</label><div class="input-group"><input class="form-control" type="password" name="token" required maxlength="4096" autocomplete="new-password"><button class="btn btn-outline-primary">Replace</button></div></form>
<form class="mt-2" method="POST" action="{{ route('gaming-hub-panel.admin.providers.credentials.remove',[$game,$server,$provider,'runtime']) }}" onsubmit="return confirm('Remove this Client token?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ $legacy ? 'Remove legacy runtime Client token' : 'Remove Client token override' }}</button></form>
@if($legacy)<p class="small text-muted mt-3 mb-0">These encrypted v0.1.x credentials remain supported until this provider is explicitly migrated to a global Panel Connection.</p>@endif
</div></div>
@endcan @endcan
@endsection

@extends('admin.layouts.admin')

@section('title', 'Edit Panel Connection')

@section('content')
<div class="row g-3">
    <div class="col-xl-8">
        <form class="card" method="POST" action="{{ route('gaming-hub-panel.admin.connections.update', $connection) }}">
            <div class="card-header">Connection details: {{ $connection->name }}</div>
            <div class="card-body">@include('gaming-hub-panel::admin.connections.partials.form')</div>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Safe status</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7">Application key</dt><dd class="col-5">{{ $tokenPresence['application'] ? 'Configured' : 'Missing' }}</dd>
                    <dt class="col-7">Default Client token</dt><dd class="col-5">{{ $tokenPresence['default_client'] ? 'Configured' : 'Missing' }}</dd>
                    <dt class="col-7">Last test</dt><dd class="col-5">{{ $connection->last_test_status ?? 'Not tested' }}</dd>
                    <dt class="col-7">Diagnostic code</dt><dd class="col-5"><code>{{ $connection->last_test_code ?? 'none' }}</code></dd>
                    <dt class="col-7">Provider mappings</dt><dd class="col-5">{{ $mappingCount }}</dd>
                </dl>
            </div>
        </div>

        <div class="d-grid gap-2 mb-3">
            @can('gaminghub-panel.connections.test')
                <form method="POST" action="{{ route('gaming-hub-panel.admin.connections.test', $connection) }}">@csrf<button class="btn btn-outline-success w-100">Test Application API</button></form>
            @endcan
            @can('gaminghub-panel.servers.discover')
                <form method="POST" action="{{ route('gaming-hub-panel.admin.connections.discover', $connection) }}">@csrf<button class="btn btn-outline-primary w-100" @disabled(!$connection->enabled)>Discover / refresh servers</button></form>
            @endcan
        </div>

        @can('gaminghub-panel.connections.manage')
        <div class="card mb-3">
            <div class="card-header">Explicit credential actions</div>
            <div class="card-body">
                @foreach(['application'=>'Application API key','default-client'=>'Default Client API token'] as $slot=>$label)
                    <form class="mb-2" method="POST" action="{{ route('gaming-hub-panel.admin.connections.credentials.replace', [$connection, $slot]) }}">
                        @csrf @method('PUT')
                        <label class="form-label">Replace {{ $label }}</label>
                        <div class="input-group"><input class="form-control" type="password" name="token" required maxlength="4096" autocomplete="new-password"><button class="btn btn-outline-primary">Replace</button></div>
                    </form>
                    <form class="mb-3" method="POST" action="{{ route('gaming-hub-panel.admin.connections.credentials.remove', [$connection, $slot]) }}" onsubmit="return confirm('Remove this stored credential?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Remove {{ $label }}</button>
                    </form>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('gaming-hub-panel.admin.connections.destroy', $connection) }}" onsubmit="return confirm('Delete this Panel Connection and its discovery cache?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger w-100" @disabled($mappingCount > 0)>Delete Connection</button>
        </form>
        @endcan
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Discovered servers</div>
    <div class="card-body">
        @if($connection->discovered_servers_count === 0)
            <div class="alert alert-info mb-0">No discovery has been performed. Test the Application API, then refresh discovery.</div>
        @else
            <div class="table-responsive"><table class="table table-striped align-middle">
                <thead><tr><th>Name</th><th>Stable identifier</th><th>Node</th><th>Allocation</th><th>Suspended</th><th>Availability</th><th>Discovered</th></tr></thead>
                <tbody>@foreach($servers as $server)<tr>
                    <td>{{ $server->name }}</td>
                    <td><code>{{ $server->stable_identifier }}</code></td>
                    <td>{{ $server->node_name ?? 'Not supplied' }}</td>
                    <td>{{ $server->primary_allocation ?? 'Not supplied' }}</td>
                    <td>{{ $server->suspended === null ? 'Unknown' : trans_bool($server->suspended) }}</td>
                    <td><span class="badge bg-{{ $server->available ? 'success' : 'secondary' }}">{{ $server->available ? 'Available' : 'Missing from latest refresh' }}</span></td>
                    <td>{{ $server->discovered_at }}</td>
                </tr>@endforeach</tbody>
            </table></div>
            {{ $servers->links() }}
        @endif
    </div>
</div>
@endsection

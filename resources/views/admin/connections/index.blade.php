@extends('admin.layouts.admin')

@section('title', 'Gaming Hub Panel Connections')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Configured Panel Connections</span>
        @can('gaminghub-panel.connections.manage')
            <a class="btn btn-primary btn-sm" href="{{ route('gaming-hub-panel.admin.connections.create') }}"><i class="bi bi-plus-lg"></i> Add Connection</a>
        @endcan
    </div>
    <div class="card-body">
        <p class="text-muted">Configure each Pelican or Pterodactyl panel once. Gaming Hub Server providers map to a connection and one discovered server.</p>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th>Name</th><th>Type</th><th>Enabled</th><th>Test status</th><th>Discovered</th><th>Mappings</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($connections as $connection)
                    <tr>
                        <td>{{ $connection->name }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($connection->panel_type) }}</span></td>
                        <td>{{ trans_bool($connection->enabled) }}</td>
                        <td>
                            @if($connection->last_test_status === 'success')
                                <span class="badge bg-success">Healthy</span>
                            @elseif($connection->last_test_status === 'failed')
                                <span class="badge bg-danger">Failed: {{ $connection->last_test_code }}</span>
                            @else
                                <span class="badge bg-secondary">Not tested</span>
                            @endif
                        </td>
                        <td>{{ $connection->available_servers_count }} available / {{ $connection->discovered_servers_count }} cached</td>
                        <td>{{ $mappingCounts[$connection->id] ?? 0 }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('gaming-hub-panel.admin.connections.edit', $connection) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted">No Panel Connections are configured.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

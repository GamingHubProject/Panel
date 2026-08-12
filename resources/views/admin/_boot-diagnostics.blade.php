<div class="card mb-3">
    <div class="card-header">Boot compatibility and connection health</div>
    <div class="card-body">
        @if($bootDiagnostics['compatible'])
            <div class="alert alert-success mb-3">Gaming Hub Panel completed its compatibility check.</div>
        @else
            <div class="alert alert-danger mb-3">
                <strong>Gaming Hub Panel is not fully active.</strong>
                {{ $bootDiagnostics['failure_reason'] ?? 'No safe failure reason was recorded.' }}
            </div>
        @endif

        <dl class="row mb-0">
            <dt class="col-sm-5">Panel plugin version</dt>
            <dd class="col-sm-7"><code>{{ $bootDiagnostics['panel_version'] }}</code></dd>

            <dt class="col-sm-5">Detected Core version</dt>
            <dd class="col-sm-7"><code>{{ $bootDiagnostics['core_version'] ?? 'Not detected' }}</code></dd>

            <dt class="col-sm-5">Compatibility result</dt>
            <dd class="col-sm-7">{{ $bootDiagnostics['compatible'] ? 'Compatible' : 'Unavailable / incompatible' }} ({{ $bootDiagnostics['compatibility_code'] }})</dd>

            <dt class="col-sm-5">Routes registered</dt>
            <dd class="col-sm-7">{{ ($bootDiagnostics['routes_registered'] ?? false) ? 'Yes' : 'No' }}</dd>

            <dt class="col-sm-5">Pelican provider type registered</dt>
            <dd class="col-sm-7">{{ ($bootDiagnostics['pelican_provider_type_registered'] ?? false) ? 'Yes' : 'No' }}</dd>

            <dt class="col-sm-5">Pterodactyl provider type registered</dt>
            <dd class="col-sm-7">{{ ($bootDiagnostics['pterodactyl_provider_type_registered'] ?? false) ? 'Yes' : 'No' }}</dd>

            <dt class="col-sm-5">Configured Panel Connections</dt>
            <dd class="col-sm-7">{{ $bootDiagnostics['configured'] ?? 'Not queried' }}</dd>

            <dt class="col-sm-5">Healthy Panel Connections</dt>
            <dd class="col-sm-7">{{ $bootDiagnostics['healthy'] ?? 'Not queried' }}</dd>

            <dt class="col-sm-5">Failed Panel Connections</dt>
            <dd class="col-sm-7">{{ $bootDiagnostics['failed'] ?? 'Not queried' }}</dd>
        </dl>

        <p class="small text-muted mt-3 mb-0">
            Provider type registration only confirms that the extension booted. It does not mean a Panel Connection is configured, tested successfully, or mapped to a Gaming Hub Server.
        </p>
    </div>
</div>

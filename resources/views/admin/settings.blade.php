@extends('admin.layouts.admin')

@section('title', 'Gaming Hub Panel Settings')

@section('content')
    @include('gaming-hub-panel::admin._boot-diagnostics')

    <form class="card" method="POST" action="{{ route('gaming-hub-panel.admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="card-header">Network and security defaults</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="default_timeout">Default timeout (seconds)</label>
                    <input class="form-control" id="default_timeout" type="number" min="2" max="30" name="default_timeout" value="{{ old('default_timeout', $settings['default_timeout']) }}" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="default_ttl">Default cache TTL (seconds)</label>
                    <input class="form-control" id="default_ttl" type="number" min="5" max="300" name="default_ttl" value="{{ old('default_ttl', $settings['default_ttl']) }}" required>
                </div>
            </div>

            @foreach([
                'default_tls_verify' => 'Verify TLS certificates by default for new Panel Connections',
                'allow_private_hosts' => 'Allow explicitly trusted private/LAN panel hosts',
                'allow_insecure_http' => 'Allow insecure HTTP panel URLs',
                'prerelease_warnings' => 'Show prerelease compatibility warnings',
            ] as $key => $label)
                <input type="hidden" name="{{ $key }}" value="0">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $settings[$key]))>
                    <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                </div>
            @endforeach

            <div class="alert alert-warning">Private hosts, insecure HTTP, or disabled TLS verification expand the server-side request trust boundary. Use them only for administrator-controlled panels.</div>
            <button class="btn btn-primary" type="submit">Save settings</button>
        </div>
    </form>
@endsection

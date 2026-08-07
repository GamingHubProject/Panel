@extends('layouts.app')

@section('title', $currentServer->name)

@section('content')
<div class="gh-server-detail" data-gh-core-server-view="{{ $gamingHubCoreServerViewVersion }}">
    <header class="gh-server-detail__hero {{ $currentServer->bannerUrl ? 'gh-server-detail__hero--image' : 'gh-server-detail__hero--fallback' }}"
        @if($currentServer->bannerUrl) style="background-image: linear-gradient(90deg, rgba(9, 13, 22, .92), rgba(9, 13, 22, .35)), url('{{ $currentServer->bannerUrl }}');" @endif>
        <div class="gh-server-detail__identity">
            @if($currentServer->iconUrl)
                <img src="{{ $currentServer->iconUrl }}" width="80" height="80" class="gh-server-detail__icon" alt="">
            @else
                <div class="gh-server-detail__icon gh-server-detail__icon--fallback" aria-hidden="true">
                    <i class="bi bi-hdd-rack"></i>
                </div>
            @endif

            <div>
                <p class="mb-1">
                    <a href="{{ route('gaming-hub-core.games.show', $currentGame->slug) }}">{{ $currentGame->name }}</a>
                </p>
                <h1 class="mb-2">{{ $currentServer->name }}</h1>
                @if($gamePageSettings['show_status'] && filled($currentServer->status))
                    <span class="badge gh-status gh-status--{{ $currentServer->status }}">
                        {{ trans('gaming-hub-core::public.statuses.'.$currentServer->status) }}
                    </span>
                @endif
            </div>
        </div>
    </header>

    <div class="row g-4 mt-1">
        <main class="col-lg-8">
            @if($currentServer->shortDescription)
                <section class="card gh-server-detail__section mb-4">
                    <div class="card-body">
                        <p class="mb-0">{{ $currentServer->shortDescription }}</p>
                    </div>
                </section>
            @endif

            @if($currentServer->longDescription)
                <section class="card gh-server-detail__section mb-4">
                    <div class="card-body">
                        <h2 class="h5">{{ trans('gaming-hub-core::public.description') }}</h2>
                        <div>{!! nl2br(e($currentServer->longDescription)) !!}</div>
                    </div>
                </section>
            @endif

            @if($currentServer->currentPlayers !== null || filled($currentServer->sourceLabel))
                <section class="card gh-server-detail__section mb-4"><div class="card-body">
                    @if($currentServer->currentPlayers !== null)<p class="mb-1">{{ $currentServer->currentPlayers }}@if($currentServer->maximumPlayers !== null) / {{ $currentServer->maximumPlayers }}@endif players</p>@endif
                    @if(filled($currentServer->sourceLabel))<p class="small text-muted mb-0">Source: {{ $currentServer->sourceLabel }}</p>@endif
                </div></section>
            @endif

            @if($gamePageSettings['show_provider_message'] && $currentServer->statusMessage)
                <section class="card gh-server-detail__section mb-4">
                    <div class="card-body">
                        <p class="text-muted mb-0">{{ $currentServer->statusMessage }}</p>
                    </div>
                </section>
            @endif
        </main>

        @php
            $ghPanelMetrics = null;
            $ghPanelStatus = null;
            if (class_exists(\Azuriom\Plugin\GamingHubPanel\Support\CoreCompatibility::class) && app(\Azuriom\Plugin\GamingHubPanel\Support\CoreCompatibility::class)->available()) {
                try {
                    $ghPanelServerModel = \Azuriom\Plugin\GamingHubCore\Models\Server::query()->find($currentServer->id);
                    if ($ghPanelServerModel) {
                        $ghPanelGateway = app(\Azuriom\Plugin\GamingHubCore\Contracts\SharedDataGateway::class);
                        $ghPanelMetrics = $ghPanelGateway->publicRead($ghPanelServerModel, 'metrics');
                        $ghPanelStatus = $ghPanelGateway->publicRead($ghPanelServerModel, 'server-status');
                    }
                } catch (\Throwable) { $ghPanelMetrics = null; $ghPanelStatus = null; }
            }
            $ghPanelHasMetrics = $ghPanelMetrics && $ghPanelMetrics->status === 'available' && count($ghPanelMetrics->data) > 0;
            $ghPanelUptime = $ghPanelStatus?->data['uptime.seconds'] ?? null;
        @endphp
        @if($ghPanelHasMetrics || $ghPanelUptime !== null)
            <section class="card gh-server-detail__section mb-4"><div class="card-body"><h2 class="h5">Live resources</h2><dl class="row mb-0">
                @if(isset($ghPanelMetrics->data['resources.cpu_percent']))<dt class="col-6">CPU</dt><dd class="col-6">{{ number_format($ghPanelMetrics->data['resources.cpu_percent'], 2) }}%</dd>@endif
                @if(isset($ghPanelMetrics->data['resources.memory_used_bytes']))<dt class="col-6">Memory used</dt><dd class="col-6">{{ number_format($ghPanelMetrics->data['resources.memory_used_bytes'] / 1048576, 1) }} MiB</dd>@endif
                @if(isset($ghPanelMetrics->data['resources.memory_limit_bytes']))<dt class="col-6">Memory limit</dt><dd class="col-6">{{ number_format($ghPanelMetrics->data['resources.memory_limit_bytes'] / 1048576, 1) }} MiB</dd>@endif
                @if(isset($ghPanelMetrics->data['resources.disk_used_bytes']))<dt class="col-6">Disk used</dt><dd class="col-6">{{ number_format($ghPanelMetrics->data['resources.disk_used_bytes'] / 1073741824, 2) }} GiB</dd>@endif
                @if($ghPanelUptime !== null)<dt class="col-6">Uptime</dt><dd class="col-6">{{ number_format($ghPanelUptime) }} seconds</dd>@endif
            </dl>@if(filled($ghPanelMetrics?->sourceLabel) || filled($ghPanelStatus?->sourceLabel))<p class="small text-muted mb-0">Source: {{ $ghPanelMetrics?->sourceLabel ?? $ghPanelStatus?->sourceLabel }}</p>@endif</div></section>
        @endif

        <aside class="col-lg-4">
            @php
                $showAddress = $gamePageSettings['show_address'] !== 'hidden' && $currentServer->hostname;
                $showPort = $gamePageSettings['show_address'] === 'hostname_and_port' && $currentServer->displayPort;
                $validJoinUrl = $currentServer->joinUrl && filter_var($currentServer->joinUrl, FILTER_VALIDATE_URL);
            @endphp

            @if($showAddress || ($gamePageSettings['show_join_button'] && $validJoinUrl))
                <section class="card gh-server-detail__actions mb-4">
                    <div class="card-body">
                        @if($showAddress)
                            <div class="mb-3">
                                <span class="text-muted d-block">{{ trans('gaming-hub-core::public.address') }}</span>
                                <code>{{ $currentServer->hostname }}@if($showPort):{{ $currentServer->displayPort }}@endif</code>
                            </div>
                        @endif

                        @if($gamePageSettings['show_join_button'] && $validJoinUrl)
                            <a href="{{ $currentServer->joinUrl }}" class="btn btn-primary w-100">
                                {{ trans('gaming-hub-core::public.join') }}
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            @if($gamePageSettings['show_navigation'] && count($gameNavigation) > 0)
                <nav class="card gh-game-navigation" aria-label="{{ trans('gaming-hub-core::public.navigation.label') }}">
                    <div class="card-body">
                        @foreach($gameNavigation as $navigationItem)
                            <a href="{{ $navigationItem['url'] }}"
                               class="gh-game-navigation__item {{ $navigationItem['active'] ? 'active' : '' }}"
                               @if($navigationItem['active']) aria-current="page" @endif>
                                @if($navigationItem['icon'])<i class="{{ $navigationItem['icon'] }}" aria-hidden="true"></i>@endif
                                <span>{{ $navigationItem['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif
        </aside>
    </div>
</div>

@push('styles')
<style>
.gh-server-detail{width:100%;}
.gh-server-detail__hero{position:relative;min-height:220px;padding:2rem;border-radius:.75rem;background-size:cover;background-position:center;display:flex;align-items:flex-end;overflow:hidden;}
.gh-server-detail__hero--fallback{background:radial-gradient(circle at top right,rgba(96,114,255,.24),transparent 42%),linear-gradient(135deg,#171d2c,#0e121c);}
.gh-server-detail__identity{display:flex;gap:1rem;align-items:center;position:relative;z-index:1;}
.gh-server-detail__icon{flex:0 0 auto;border-radius:.75rem;object-fit:cover;background:rgba(255,255,255,.08);}
.gh-server-detail__icon--fallback{display:grid;place-items:center;font-size:2rem;}
.gh-server-detail__section{height:auto;}
.gh-game-navigation__item{display:flex;gap:.65rem;align-items:center;padding:.7rem .75rem;border-radius:.5rem;text-decoration:none;}
.gh-game-navigation__item.active{font-weight:600;}
@media (max-width:767.98px){.gh-server-detail__hero{min-height:180px;padding:1.25rem}.gh-server-detail__identity{align-items:flex-start}.gh-server-detail__icon{width:64px;height:64px}}
</style>
@endpush
@endsection

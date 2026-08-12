@csrf
@if(isset($provider)) @method('PUT') @endif
@php($selectedType=old('provider_type',$provider->provider_type??($types[0]->id??null)))
@php($panelTypes=['pelican','pterodactyl'])
@php($canViewPanelConnections=auth()->user()?->can('gaminghub-panel.connections.view')===true)

<div class="mb-3">
    <label class="form-label" for="provider_type">{{ trans('gaming-hub-core::admin.fields.provider_type') }}</label>
    <select class="form-select @error('provider_type') is-invalid @enderror" id="provider_type" name="provider_type" required @disabled(isset($provider))>
        @foreach($types as $type)<option value="{{ $type->id }}" @selected($selectedType===$type->id)>{{ $type->name }} ({{ $type->id }})</option>@endforeach
    </select>
    @if(isset($provider))<input type="hidden" name="provider_type" value="{{ $provider->provider_type }}">@endif
    @error('provider_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="name">{{ trans('gaming-hub-core::admin.fields.name') }}</label>
    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" maxlength="255" required value="{{ old('name',$provider->name??'') }}">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label class="form-label" for="position">{{ trans('gaming-hub-core::admin.fields.order') }}</label>
    <input class="form-control @error('position') is-invalid @enderror" type="number" id="position" name="position" value="{{ old('position',$provider->position??0) }}" required>
    @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="form-check mb-3">
    <input type="hidden" name="enabled" value="0">
    <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" @checked(old('enabled',$provider->enabled??true))>
    <label class="form-check-label" for="enabled">{{ trans('gaming-hub-core::admin.fields.enabled') }}</label>
</div>

@foreach($types as $type)
<fieldset class="provider-config border rounded p-3 mb-3" data-provider-type="{{ $type->id }}">
    <legend class="h6">{{ $type->name }}</legend>
    <p class="text-muted">{{ $type->description }}</p>

    @if(in_array($type->id,$panelTypes,true))
        @php($cfg=($provider->provider_type??null)===$type->id?(array)($provider->configuration??[]):[])
        @php($legacy=isset($provider)&&($provider->provider_type??null)===$type->id&&!array_key_exists('panel_connection_id',$cfg))
        @php($mappingMode=old('configuration.mapping_mode',$legacy?'legacy':'connection'))
        @php($currentConnectionId=(int)old('configuration.panel_connection_id',data_get($cfg,'panel_connection_id',0)))
        @php($connections=$canViewPanelConnections?\Azuriom\Plugin\GamingHubPanel\Models\PanelConnectionProfile::query()->where('panel_type',$type->id)->orderBy('name')->get()->filter(fn($item)=>$item->enabled||(int)$item->id===$currentConnectionId):collect())
        @php($currentConnection=$connections->first(fn($item)=>(int)$item->id===$currentConnectionId))
        @php($currentIdentifier=(string)old('configuration.panel_server_identifier',data_get($cfg,'panel_server_identifier','')))
        @php($currentDiscovered=$currentConnection ? \Azuriom\Plugin\GamingHubPanel\Models\DiscoveredPanelServer::query()->where('connection_id',$currentConnection->id)->where('stable_identifier',$currentIdentifier)->first():null)
        @php($selectedServerId=(int)old('configuration.discovered_server_id',$currentDiscovered?->id??0))
        @php($manual=(bool)old('configuration.manual_identifier',data_get($cfg,'manual_identifier',false)))
        @php($overridePresent=isset($provider)?app(\Azuriom\Plugin\GamingHubPanel\Services\PanelCredentialStore::class)->presence((int)$provider->id)['runtime']:false)

        @if($legacy)
            <div class="alert alert-warning">
                <strong>Legacy direct configuration.</strong> Its panel URL and encrypted v0.1.x credentials remain preserved and continue to work. Select “Migrate to a global connection” to move this provider to the v0.2 mapping model without recreating it.
            </div>
            <div class="mb-3">
                <label class="form-label" for="mapping_mode_{{ $type->id }}">Configuration mode</label>
                <select class="form-select mapping-mode" id="mapping_mode_{{ $type->id }}" name="configuration[mapping_mode]">
                    <option value="legacy" @selected($mappingMode==='legacy')>Keep legacy direct configuration</option>
                    <option value="connection" @selected($mappingMode==='connection')>Migrate to a global Panel Connection</option>
                </select>
            </div>
        @else
            <input type="hidden" name="configuration[mapping_mode]" value="connection">
        @endif

        <div class="mapping-fields" @if($legacy&&$mappingMode==='legacy') hidden @endif>
            <div class="mb-3">
                <label class="form-label" for="connection_{{ $type->id }}">Panel connection</label>
                <select class="form-select panel-connection @error('configuration.panel_connection_id') is-invalid @enderror" id="connection_{{ $type->id }}" name="configuration[panel_connection_id]">
                    <option value="">Select a {{ $type->name }} connection…</option>
                    @foreach($connections as $connection)
                        <option value="{{ $connection->id }}" data-servers-url="{{ route('gaming-hub-panel.admin.connections.servers',$connection) }}" @selected($currentConnectionId===(int)$connection->id) @disabled(!$connection->enabled && $currentConnectionId!==(int)$connection->id)>
                            {{ $connection->name }}{{ $connection->enabled?'':' (disabled)' }}
                        </option>
                    @endforeach
                </select>
                @error('configuration.panel_connection_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">
                    @if($canViewPanelConnections)
                        Only enabled {{ $type->name }} connections are selectable.
                        <a href="{{ route('gaming-hub-panel.admin.connections.index') }}">Manage Panel Connections</a>
                    @else
                        You need the Gaming Hub Panel Connections view permission before a panel mapping can be configured.
                    @endif
                </div>
            </div>

            <div class="mb-3 discovered-server-group" @if($manual) hidden @endif>
                <label class="form-label" for="server_{{ $type->id }}">Panel server</label>
                <select class="form-select panel-server @error('configuration.discovered_server_id') is-invalid @enderror" id="server_{{ $type->id }}" name="configuration[discovered_server_id]" data-selected="{{ $selectedServerId }}">
                    <option value="">{{ $currentConnection?'Loading discovered servers…':'Select a Panel Connection first…' }}</option>
                    @if($currentDiscovered)
                        <option value="{{ $currentDiscovered->id }}" data-identifier="{{ $currentDiscovered->stable_identifier }}" selected>{{ $currentDiscovered->name }}{{ $currentDiscovered->available?'':' (missing from latest discovery)' }}</option>
                    @endif
                </select>
                @error('configuration.discovered_server_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text server-empty-state">Discovery is preferred. Refresh servers from the selected connection’s edit page when this list is empty.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="stored_identifier_{{ $type->id }}">Stored panel server identifier</label>
                <input class="form-control stored-identifier" id="stored_identifier_{{ $type->id }}" maxlength="64" value="{{ $currentIdentifier }}" readonly>
                <div class="form-text">Filled automatically from the selected discovered server and stored independently of the discovery cache.</div>
            </div>

            <div class="form-check mb-3">
                <input type="hidden" name="configuration[manual_identifier]" value="0">
                <input class="form-check-input manual-toggle" id="manual_{{ $type->id }}" type="checkbox" name="configuration[manual_identifier]" value="1" @checked($manual)>
                <label class="form-check-label" for="manual_{{ $type->id }}">Advanced: enter server identifier manually</label>
            </div>
            <div class="manual-identifier mb-3" @if(!$manual) hidden @endif>
                <label class="form-label" for="manual_identifier_{{ $type->id }}">Manual server identifier</label>
                <input class="form-control @error('configuration.manual_server_identifier') is-invalid @enderror" id="manual_identifier_{{ $type->id }}" name="configuration[manual_server_identifier]" maxlength="64" value="{{ old('configuration.manual_server_identifier',$currentIdentifier) }}">
                <div class="form-text text-warning">Discovery is preferred. Manual identifiers are validated for the selected panel type and rejected when known to belong to another connection.</div>
                @error('configuration.manual_server_identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="client_override_{{ $type->id }}">Client API token override {{ isset($provider)?'(leave blank to preserve)':'(optional)' }}</label>
                <input class="form-control @error('client_token_override') is-invalid @enderror" id="client_override_{{ $type->id }}" type="password" name="client_token_override" maxlength="4096" autocomplete="new-password">
                <div class="form-text">Resolution order: this override → connection default Client token → <code>configuration_invalid</code>. Existing override: {{ $overridePresent?'configured':'not configured' }}.</div>
                @error('client_token_override')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="attribution_{{ $type->id }}">Public attribution label</label>
                <input class="form-control @error('configuration.public_attribution_label') is-invalid @enderror" id="attribution_{{ $type->id }}" maxlength="100" name="configuration[public_attribution_label]" value="{{ old('configuration.public_attribution_label',data_get($cfg,'public_attribution_label')) }}">
                <div class="form-text">Used only when Gaming Hub Core policy explicitly enables attribution.</div>
                @error('configuration.public_attribution_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="timeout_{{ $type->id }}">Timeout override</label>
                    <input class="form-control @error('configuration.request_timeout') is-invalid @enderror" id="timeout_{{ $type->id }}" type="number" min="2" max="30" name="configuration[request_timeout]" value="{{ old('configuration.request_timeout',data_get($cfg,'request_timeout')) }}" placeholder="Inherit connection default">
                    @error('configuration.request_timeout')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="ttl_{{ $type->id }}">Cache TTL override</label>
                    <input class="form-control @error('configuration.cache_ttl') is-invalid @enderror" id="ttl_{{ $type->id }}" type="number" min="5" max="300" name="configuration[cache_ttl]" value="{{ old('configuration.cache_ttl',data_get($cfg,'cache_ttl')) }}" placeholder="Inherit connection default">
                    @error('configuration.cache_ttl')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @else
        @foreach($type->fields as $field)
            @php($value=old('configuration.'.$field->key,data_get($provider->configuration??[],$field->key)))
            <div class="mb-3">
                <label class="form-label">{{ $field->label }}</label>
                @if($field->type==='boolean')
                    <input type="hidden" name="configuration[{{ $field->key }}]" value="0"><input class="form-check-input" type="checkbox" name="configuration[{{ $field->key }}]" value="1" @checked($value)>
                @elseif($field->type==='select')
                    <select class="form-select" name="configuration[{{ $field->key }}]">@foreach($field->options as $option)<option value="{{ $option }}" @selected($value===$option)>{{ $option }}</option>@endforeach</select>
                @else
                    <input class="form-control" type="{{ $field->secret?'password':($field->type==='integer'?'number':'text') }}" name="configuration[{{ $field->key }}]" value="{{ $field->secret?'':$value }}" @if($field->maxLength) maxlength="{{ $field->maxLength }}" @endif @required($field->required)>
                @endif
                @if($field->help)<div class="form-text">{{ $field->help }}</div>@endif
                @error('configuration.'.$field->key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        @endforeach
    @endif
</fieldset>
@endforeach

<button class="btn btn-primary" type="submit">{{ trans('gaming-hub-core::admin.actions.save') }}</button>
<a class="btn btn-secondary" href="{{ route('gaming-hub-core.admin.games.servers.providers.index',[$game,$server]) }}">{{ trans('gaming-hub-core::admin.actions.cancel') }}</a>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const typeSelect=document.getElementById('provider_type');
    const syncType=()=>document.querySelectorAll('.provider-config').forEach(fieldset=>{
        const active=fieldset.dataset.providerType===typeSelect.value;
        fieldset.hidden=!active;
        fieldset.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=!active);
        if(active) syncFieldset(fieldset);
    });
    const syncFieldset=fieldset=>{
        const mode=fieldset.querySelector('.mapping-mode');
        const mapping=fieldset.querySelector('.mapping-fields');
        if(mode&&mapping){mapping.hidden=mode.value==='legacy';mapping.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=mode.value==='legacy');}
        const manual=fieldset.querySelector('.manual-toggle');
        const manualBox=fieldset.querySelector('.manual-identifier');
        const discovered=fieldset.querySelector('.discovered-server-group');
        const stored=fieldset.querySelector('.stored-identifier');
        const manualInput=manualBox?.querySelector('input');
        if(manual&&manualBox&&discovered&&stored&&manualInput){manualBox.hidden=!manual.checked;discovered.hidden=manual.checked;manualInput.disabled=!manual.checked;if(manual.checked)stored.value=manualInput.value;}
    };
    const loadServers=async(fieldset)=>{
        const connection=fieldset.querySelector('.panel-connection');
        const server=fieldset.querySelector('.panel-server');
        const stored=fieldset.querySelector('.stored-identifier');
        const selectedOption=connection?.selectedOptions[0];
        const url=selectedOption?.dataset.serversUrl;
        if(!connection||!server||!stored)return;
        server.innerHTML='<option value="">Select a Panel Connection first…</option>';stored.value='';
        if(!url)return;
        server.innerHTML='<option value="">Loading discovered servers…</option>';
        try{
            const response=await fetch(url,{headers:{'Accept':'application/json'}});const data=await response.json();if(!response.ok)throw new Error(data.error||'unavailable');
            server.innerHTML='<option value="">Select a discovered server…</option>';
            data.servers.forEach(item=>{const option=document.createElement('option');option.value=item.id;option.dataset.identifier=item.stable_identifier;option.textContent=item.name+' — '+item.stable_identifier;if(String(item.id)===String(server.dataset.selected))option.selected=true;server.appendChild(option);});
            if(!data.servers.length)server.innerHTML='<option value="">No discovery data. Refresh this connection first.</option>';
            const chosen=server.selectedOptions[0];if(chosen?.dataset.identifier)stored.value=chosen.dataset.identifier;
        }catch(error){server.innerHTML='<option value="">Unable to load discovered servers.</option>';}
    };
    document.querySelectorAll('.provider-config').forEach(fieldset=>{
        fieldset.querySelector('.mapping-mode')?.addEventListener('change',()=>syncFieldset(fieldset));
        fieldset.querySelector('.manual-toggle')?.addEventListener('change',()=>syncFieldset(fieldset));
        const manualInput=fieldset.querySelector('.manual-identifier input');manualInput?.addEventListener('input',()=>{const stored=fieldset.querySelector('.stored-identifier');if(stored)stored.value=manualInput.value;});
        fieldset.querySelector('.panel-connection')?.addEventListener('change',()=>loadServers(fieldset));
        fieldset.querySelector('.panel-server')?.addEventListener('change',event=>{const option=event.target.selectedOptions[0],stored=fieldset.querySelector('.stored-identifier');if(stored)stored.value=option?.dataset.identifier||'';});
        const initialServer=fieldset.querySelector('.panel-server');if(fieldset.querySelector('.panel-connection')?.value&&!initialServer?.querySelector('option[selected]'))loadServers(fieldset);
    });
    typeSelect.addEventListener('change',syncType);syncType();
});
</script>
@endpush

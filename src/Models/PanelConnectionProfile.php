<?php

namespace Azuriom\Plugin\GamingHubPanel\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PanelConnectionProfile extends Model
{
    use HasTablePrefix;

    protected $prefix = 'gaminghub_';
    protected $table = 'gaminghub_panel_connections';

    protected $fillable = [
        'uuid',
        'name',
        'panel_type',
        'base_url',
        'encrypted_application_token',
        'encrypted_default_client_token',
        'timeout',
        'cache_ttl',
        'verify_tls',
        'enabled',
        'last_test_status',
        'last_test_code',
        'last_test_latency_ms',
        'last_tested_at',
        'last_successful_test_at',
    ];

    protected $hidden = [
        'encrypted_application_token',
        'encrypted_default_client_token',
    ];

    protected $casts = [
        'timeout' => 'integer',
        'cache_ttl' => 'integer',
        'verify_tls' => 'boolean',
        'enabled' => 'boolean',
        'last_test_latency_ms' => 'integer',
        'last_tested_at' => 'immutable_datetime',
        'last_successful_test_at' => 'immutable_datetime',
    ];

    public function discoveredServers(): HasMany
    {
        return $this->hasMany(DiscoveredPanelServer::class, 'connection_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function hasApplicationToken(): bool
    {
        return filled($this->encrypted_application_token);
    }

    public function hasDefaultClientToken(): bool
    {
        return filled($this->encrypted_default_client_token);
    }
}

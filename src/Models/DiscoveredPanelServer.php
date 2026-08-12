<?php

namespace Azuriom\Plugin\GamingHubPanel\Models;

use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DiscoveredPanelServer extends Model
{
    use HasTablePrefix;

    protected $prefix = 'gaminghub_';
    protected $table = 'gaminghub_panel_discovered_servers';

    protected $fillable = [
        'connection_id',
        'stable_identifier',
        'uuid',
        'short_identifier',
        'name',
        'node_name',
        'suspended',
        'primary_allocation',
        'available',
        'discovered_at',
        'missing_since',
        'metadata',
    ];

    protected $casts = [
        'connection_id' => 'integer',
        'suspended' => 'boolean',
        'available' => 'boolean',
        'discovered_at' => 'immutable_datetime',
        'missing_since' => 'immutable_datetime',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(PanelConnectionProfile::class, 'connection_id');
    }
}

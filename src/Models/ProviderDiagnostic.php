<?php
namespace Azuriom\Plugin\GamingHubPanel\Models;
use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;
final class ProviderDiagnostic extends Model
{
    use HasTablePrefix;
    protected $prefix='gaminghub_'; protected $table='gaminghub_panel_diagnostics';
    protected $fillable=['provider_id','last_attempted_at','last_successful_at','last_error_category','detected_version','last_state','last_latency_ms'];
    protected $casts=['provider_id'=>'integer','last_attempted_at'=>'immutable_datetime','last_successful_at'=>'immutable_datetime','last_latency_ms'=>'integer'];
}

<?php
namespace Azuriom\Plugin\GamingHubPanel\Models;
use Azuriom\Models\Traits\HasTablePrefix;
use Illuminate\Database\Eloquent\Model;
final class PanelCredential extends Model
{
    use HasTablePrefix;
    protected $prefix='gaminghub_'; protected $table='gaminghub_panel_credentials';
    protected $fillable=['provider_id','encrypted_api_token','encrypted_runtime_token'];
    protected $hidden=['encrypted_api_token','encrypted_runtime_token'];
    protected $casts=['provider_id'=>'integer'];
}

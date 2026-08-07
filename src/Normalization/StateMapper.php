<?php
namespace Azuriom\Plugin\GamingHubPanel\Normalization;
final class StateMapper
{
    public function map(?string $state,bool $suspended=false,bool $maintenance=false): array
    {
        $raw=strtolower(trim((string)$state));
        if($suspended)return['maintenance','Server access is suspended.'];
        if($maintenance)return['maintenance','Panel maintenance operation is active.'];
        return match($raw){
            'running','online'=>['online',null],
            'offline','stopped'=>['offline',null],
            'starting','stopping','suspended','maintenance','installing','install_failed','restoring','restoring_backup','transferring','transfer_failed','reinstalling','rebuilding'=>['maintenance',ucfirst($raw).'.'],
            'unavailable','unknown',''=>['unknown',null],
            default=>['unknown','Panel returned an unrecognized runtime state.'],
        };
    }
}

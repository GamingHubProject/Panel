<?php
namespace Azuriom\Plugin\GamingHubPanel\Exceptions;
final class PanelApiException extends \RuntimeException
{
    public function __construct(public readonly string $category, string $safeMessage = 'Panel request failed.', public readonly ?int $httpStatus = null)
    { parent::__construct($safeMessage); }
}

<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector\Exceptions;

final class UnknownConnector extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('No Connector is registered with id "%s".', $id));
    }
}

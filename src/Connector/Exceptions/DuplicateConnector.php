<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector\Exceptions;

final class DuplicateConnector extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Connector "%s" is already registered.', $id));
    }
}

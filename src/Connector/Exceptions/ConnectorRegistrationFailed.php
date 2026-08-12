<?php

namespace Azuriom\Plugin\GamingHubPanel\Connector\Exceptions;

final class ConnectorRegistrationFailed extends \RuntimeException
{
    public static function idMismatch(string $connectorId, string $providerTypeId): self
    {
        return new self(sprintf(
            'Connector id "%s" does not match its own providerType()->id "%s".',
            $connectorId,
            $providerTypeId,
        ));
    }

    public static function ownershipConflict(string $providerTypeId): self
    {
        return new self(sprintf(
            'Provider type "%s" is already owned by an incompatible registration.',
            $providerTypeId,
        ));
    }

    public static function undeclaredCapability(string $connectorId, string $capability): self
    {
        return new self(sprintf(
            'Connector "%s" declares a reader for capability "%s" that its own providerType() does not declare support for.',
            $connectorId,
            $capability,
        ));
    }

    public static function incomplete(string $providerTypeId): self
    {
        return new self(sprintf('Provider type "%s" did not complete registration.', $providerTypeId));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Domain exception thrown when a requested User is not found.
 *
 * This is a domain-level concept — the "not found" is a business concern,
 * not an infrastructure concern. The HTTP 404 mapping happens in the UI layer.
 */
final class UserNotFoundException extends \DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('User with ID %d was not found', $id));
    }

    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" was not found', $email));
    }
}

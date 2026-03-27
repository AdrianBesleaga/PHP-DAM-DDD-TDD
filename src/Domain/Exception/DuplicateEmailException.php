<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Thrown when attempting to create a user with a duplicate email.
 */
final class DuplicateEmailException extends \DomainException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('A user with email "%s" already exists', $email));
    }
}

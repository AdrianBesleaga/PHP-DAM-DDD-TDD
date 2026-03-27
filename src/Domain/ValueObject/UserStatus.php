<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Enum representing the possible states of a User.
 * 
 * PHP 8.1 enums are perfect for DDD — they constrain the domain
 * to only valid states, and the backed string gives us serialization for free.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function isAllowedToLogin(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Suspended => 'Suspended',
        };
    }
}

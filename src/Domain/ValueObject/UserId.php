<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing a User's unique identifier.
 * 
 * Value Objects are immutable and compared by value, not identity.
 * This is a core DDD tactical pattern — wrapping primitive types
 * in domain-meaningful objects that enforce invariants.
 */
final readonly class UserId
{
    public function __construct(
        private int $value
    ) {
        if ($value <= 0) {
            throw new \InvalidArgumentException(
                sprintf('User ID must be a positive integer, got: %d', $value)
            );
        }
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}

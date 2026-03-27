<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing a validated email address.
 *
 * Encapsulates the validation rule so it's impossible to create
 * an Email instance with an invalid address — "make illegal states unrepresentable."
 */
final readonly class Email
{
    public function __construct(
        private string $value
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid email address: "%s"', $value)
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        return explode('@', $this->value)[1];
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

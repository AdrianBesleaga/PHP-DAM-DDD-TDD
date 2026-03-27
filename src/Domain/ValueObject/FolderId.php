<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing a Folder's unique identifier.
 */
final readonly class FolderId
{
    public function __construct(
        private int $value
    ) {
        if ($value <= 0) {
            throw new \InvalidArgumentException(
                sprintf('Folder ID must be a positive integer, got: %d', $value)
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

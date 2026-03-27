<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing a file name with extension.
 *
 * Enforces invariants:
 * - Cannot be empty
 * - Max 255 characters (filesystem limit)
 * - Must have a file extension
 * - Extracts extension for downstream use
 */
final readonly class FileName
{
    private const int MAX_LENGTH = 255;

    public function __construct(
        private string $value
    ) {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('File name cannot be empty');
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('File name cannot exceed %d characters, got: %d', self::MAX_LENGTH, strlen($trimmed))
            );
        }

        if (!str_contains($trimmed, '.')) {
            throw new \InvalidArgumentException(
                sprintf('File name must have an extension: "%s"', $trimmed)
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Extract the file extension (e.g., "jpg", "pdf").
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->value, PATHINFO_EXTENSION));
    }

    /**
     * Get the name without extension (e.g., "photo" from "photo.jpg").
     */
    public function baseName(): string
    {
        return pathinfo($this->value, PATHINFO_FILENAME);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

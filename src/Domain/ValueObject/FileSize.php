<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing a file size in bytes.
 *
 * Provides domain-useful conversions and human-readable output.
 * Enforces non-negative invariant.
 */
final readonly class FileSize
{
    private const int KB = 1024;
    private const int MB = 1024 * 1024;
    private const int GB = 1024 * 1024 * 1024;

    /** 100MB upload limit — a business rule baked into the domain. */
    private const int MAX_FILE_SIZE = 100 * 1024 * 1024;

    public function __construct(
        private int $bytes
    ) {
        if ($bytes < 0) {
            throw new \InvalidArgumentException(
                sprintf('File size cannot be negative, got: %d', $bytes)
            );
        }

        if ($bytes > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                sprintf('File size cannot exceed %s, got: %s', self::formatBytes(self::MAX_FILE_SIZE), self::formatBytes($bytes))
            );
        }
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function toKilobytes(): float
    {
        return round($this->bytes / self::KB, 2);
    }

    public function toMegabytes(): float
    {
        return round($this->bytes / self::MB, 2);
    }

    /**
     * Human-readable representation (e.g., "4.2 MB", "512 KB").
     */
    public function toHumanReadable(): string
    {
        return self::formatBytes($this->bytes);
    }

    public function isLargerThan(self $other): bool
    {
        return $this->bytes > $other->bytes;
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }

    public function __toString(): string
    {
        return $this->toHumanReadable();
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= self::GB) {
            return round($bytes / self::GB, 2) . ' GB';
        }
        if ($bytes >= self::MB) {
            return round($bytes / self::MB, 2) . ' MB';
        }
        if ($bytes >= self::KB) {
            return round($bytes / self::KB, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}

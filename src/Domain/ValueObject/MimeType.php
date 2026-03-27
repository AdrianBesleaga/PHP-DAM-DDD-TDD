<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing a MIME type.
 *
 * Validates against an allowlist of supported types and provides
 * category detection (image, video, document, audio).
 */
final readonly class MimeType
{
    /** Allowed MIME types — the DAM's supported file formats. */
    private const array ALLOWED_TYPES = [
        // Images
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        // Videos
        'video/mp4',
        'video/webm',
        'video/quicktime',
        // Documents
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        // Audio
        'audio/mpeg',
        'audio/wav',
        'audio/ogg',
    ];

    public function __construct(
        private string $value
    ) {
        if (!in_array($value, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Unsupported MIME type: "%s"', $value)
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Extract the category (e.g., "image", "video", "application", "audio").
     */
    public function category(): string
    {
        return explode('/', $this->value)[0];
    }

    public function isImage(): bool
    {
        return $this->category() === 'image';
    }

    public function isVideo(): bool
    {
        return $this->category() === 'video';
    }

    public function isDocument(): bool
    {
        return $this->category() === 'application';
    }

    public function isAudio(): bool
    {
        return $this->category() === 'audio';
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

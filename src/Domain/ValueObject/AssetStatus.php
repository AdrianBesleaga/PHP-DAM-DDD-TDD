<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Asset lifecycle status.
 *
 * State machine: Draft → Published → Archived
 *                          ↑                  |
 *                          └──────────────────┘ (restore)
 *
 * Business rules:
 * - Only Draft assets can be published
 * - Only Published assets can be archived
 * - Only Archived assets can be restored to Draft
 */
enum AssetStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Published,
            self::Published => $target === self::Archived,
            self::Archived => $target === self::Draft,
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    public function isArchived(): bool
    {
        return $this === self::Archived;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}

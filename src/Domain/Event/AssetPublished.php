<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\AssetId;

/**
 * Raised when an asset transitions from Draft to Published.
 */
final readonly class AssetPublished implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public AssetId $assetId,
        public string $fileName,
        public string $publishedByInfo,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

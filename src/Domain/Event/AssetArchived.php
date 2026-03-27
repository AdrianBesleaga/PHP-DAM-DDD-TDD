<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\AssetId;

/**
 * Raised when an asset transitions from Published to Archived.
 */
final readonly class AssetArchived implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public AssetId $assetId,
        public string $fileName,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

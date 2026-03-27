<?php

declare(strict_types=1);

namespace App\Application\EventHandler;

use App\Domain\Event\AssetPublished;
use Psr\Log\LoggerInterface;

/**
 * Example Domain Event handler.
 *
 * In a real system, this could trigger:
 * - Search index update
 * - CDN cache invalidation
 * - Notification to subscribers
 * - Webhook delivery
 *
 * The key DDD benefit: the Asset entity doesn't know about any of these.
 * It just records the event. The handler reacts independently.
 */
final class LogAssetPublishedHandler
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function handle(AssetPublished $event): void
    {
        $this->logger->info('Asset published', [
            'asset_id' => $event->assetId->value(),
            'file_name' => $event->fileName,
            'published_by' => $event->publishedByInfo,
            'occurred_at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}

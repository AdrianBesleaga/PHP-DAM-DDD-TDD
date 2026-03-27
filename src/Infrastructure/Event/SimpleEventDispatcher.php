<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

use App\Application\EventHandler\LogAssetPublishedHandler;
use App\Domain\Event\AssetPublished;
use App\Domain\Event\DomainEvent;
use App\Domain\Event\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Simple event dispatcher — routes events to their handlers.
 *
 * In a production system, you'd use a proper event bus (Symfony Messenger,
 * Laravel Events, etc.). This lightweight implementation demonstrates
 * the pattern without adding framework-level complexity.
 */
final class SimpleEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly LogAssetPublishedHandler $assetPublishedHandler,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param DomainEvent[] $events
     */
    public function dispatch(array $events): void
    {
        foreach ($events as $event) {
            $this->route($event);
        }
    }

    private function route(DomainEvent $event): void
    {
        match ($event::class) {
            AssetPublished::class => $this->assetPublishedHandler->handle($event),
            default => $this->logger->debug('Unhandled domain event', [
                'event' => $event::class,
                'occurred_at' => $event->occurredAt()->format(\DateTimeInterface::ATOM),
            ]),
        };
    }
}

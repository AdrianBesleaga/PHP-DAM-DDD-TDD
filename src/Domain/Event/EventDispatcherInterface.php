<?php

declare(strict_types=1);

namespace App\Domain\Event;

/**
 * Port (Interface) for dispatching domain events.
 *
 * The Domain defines WHAT needs to happen (dispatch events).
 * The Infrastructure decides HOW (log, queue, webhook, etc.).
 *
 * For Java devs: similar to Spring's ApplicationEventPublisher.
 */
interface EventDispatcherInterface
{
    /**
     * @param DomainEvent[] $events
     */
    public function dispatch(array $events): void;
}

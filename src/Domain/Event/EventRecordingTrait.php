<?php

declare(strict_types=1);

namespace App\Domain\Event;

/**
 * Trait for aggregate roots that record domain events.
 *
 * Events are collected during a use case and dispatched after
 * the transaction commits successfully — ensuring consistency.
 *
 * Usage: `use EventRecordingTrait;` inside an Entity class.
 */
trait EventRecordingTrait
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    protected function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return DomainEvent[]
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * @return DomainEvent[]
     */
    public function peekDomainEvents(): array
    {
        return $this->domainEvents;
    }
}

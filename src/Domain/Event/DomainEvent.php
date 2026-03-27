<?php

declare(strict_types=1);

namespace App\Domain\Event;

/**
 * Marker interface for all domain events.
 *
 * Domain Events capture "something that happened" in the domain.
 * They are immutable records — once raised, they cannot be changed.
 *
 * For Java devs: similar to Spring's ApplicationEvent.
 */
interface DomainEvent
{
    /**
     * When the event occurred.
     */
    public function occurredAt(): \DateTimeImmutable;
}

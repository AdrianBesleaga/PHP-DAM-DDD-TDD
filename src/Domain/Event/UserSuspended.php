<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\UserId;

/**
 * Raised when a user is suspended.
 */
final readonly class UserSuspended implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public UserId $userId,
        public string $userName,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}

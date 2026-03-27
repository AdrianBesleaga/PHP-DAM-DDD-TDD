<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Event\EventRecordingTrait;
use App\Domain\Event\UserSuspended;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\UserStatus;

/**
 * User Entity — an aggregate root in our domain.
 * 
 * Key DDD concepts demonstrated:
 * - Identity: Users are identified by their UserId, not by comparing all fields.
 * - Encapsulation: State changes go through methods that enforce business rules.
 * - Rich Domain Model: Business logic lives HERE, not in services.
 */
final class User
{
    use EventRecordingTrait;

    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly UserId $id,
        private string $name,
        private Email $email,
        private UserStatus $status = UserStatus::Active,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->name = trim($name);

        if ($this->name === '') {
            throw new \InvalidArgumentException('User name cannot be empty');
        }

        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = null;
    }

    // --- Accessors (no setters — state changes go through domain methods) ---

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // --- Domain Behavior ---

    /**
     * Rename a user. Business rule: names cannot be empty.
     */
    public function rename(string $newName): void
    {
        $newName = trim($newName);

        if ($newName === '') {
            throw new \InvalidArgumentException('User name cannot be empty');
        }

        if ($this->name === $newName) {
            return; // Idempotent — no change needed
        }

        $this->name = $newName;
        $this->touch();
    }

    /**
     * Change a user's email. The Email Value Object handles validation.
     */
    public function changeEmail(Email $newEmail): void
    {
        if ($this->email->equals($newEmail)) {
            return; // Idempotent — no change needed
        }

        $this->email = $newEmail;
        $this->touch();
    }

    /**
     * Suspend a user. Business rule: only active users can be suspended.
     */
    public function suspend(): void
    {
        if ($this->status !== UserStatus::Active) {
            throw new \DomainException(
                sprintf('Cannot suspend user with status "%s". Only active users can be suspended.', $this->status->value)
            );
        }

        $this->status = UserStatus::Suspended;
        $this->touch();
        $this->recordEvent(new UserSuspended($this->id, $this->name));
    }

    /**
     * Reactivate a suspended or inactive user.
     */
    public function reactivate(): void
    {
        if ($this->status === UserStatus::Active) {
            throw new \DomainException('User is already active');
        }

        $this->status = UserStatus::Active;
        $this->touch();
    }

    /**
     * Check if this user can log in.
     */
    public function canLogin(): bool
    {
        return $this->status->isAllowedToLogin();
    }

    /**
     * Serialize to a plain array for API responses.
     * This is an "anti-corruption layer" pattern — the Entity controls its own representation.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'name' => $this->name,
            'email' => $this->email->value(),
            'status' => $this->status->value,
            'can_login' => $this->canLogin(),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

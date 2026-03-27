<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;

/**
 * In-Memory implementation of UserRepositoryInterface.
 *
 * This is an "Adapter" in Hexagonal Architecture.
 * It fulfills the contract defined by the Domain's Port (interface).
 *
 * In a real application, you'd swap this for MySqlUserRepository
 * or DoctrineUserRepository — WITHOUT changing any Application or Domain code.
 *
 * This is also perfect for testing — you can use this exact class
 * in integration tests instead of mocking.
 */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<int, User> */
    private array $users = [];
    private int $nextId = 1;

    /**
     * Optionally seed with initial data.
     *
     * @param User[] $initialUsers
     */
    public function __construct(array $initialUsers = [])
    {
        foreach ($initialUsers as $user) {
            $this->users[$user->id()->value()] = $user;
            // Keep nextId ahead of any seeded IDs
            if ($user->id()->value() >= $this->nextId) {
                $this->nextId = $user->id()->value() + 1;
            }
        }
    }

    public function findById(UserId $id): ?User
    {
        return $this->users[$id->value()] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return array_values($this->users);
    }

    public function save(User $user): void
    {
        $this->users[$user->id()->value()] = $user;

        // Keep nextId ahead of any saved IDs to prevent collisions
        if ($user->id()->value() >= $this->nextId) {
            $this->nextId = $user->id()->value() + 1;
        }
    }

    public function delete(UserId $id): void
    {
        unset($this->users[$id->value()]);
    }

    public function nextId(): UserId
    {
        return new UserId($this->nextId++);
    }
}

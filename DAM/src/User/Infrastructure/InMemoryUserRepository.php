<?php
declare(strict_types=1);

namespace App\User\Infrastructure;

use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];

    public function getUserById(UserId $id): ?User
    {
        return $this->users[$id->getValue()] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[$user->getId()->getValue()] = $user;
    }
}

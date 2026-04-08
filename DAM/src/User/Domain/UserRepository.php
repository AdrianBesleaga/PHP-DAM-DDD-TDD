<?php
declare(strict_types=1);

namespace App\User\Domain;

interface UserRepository
{
    public function getUserById(UserId $id): ?User;
    public function save(User $user): void;
}

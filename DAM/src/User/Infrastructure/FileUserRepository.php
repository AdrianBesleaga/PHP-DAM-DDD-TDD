<?php
declare(strict_types=1);

namespace App\User\Infrastructure;

use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;

class FileUserRepository implements UserRepository
{
    private string $filePath;
    /** @var array<string, User> */
    private array $users = [];

    public function __construct(string $filePath = __DIR__ . '/../../../../users.db')
    {
        $this->filePath = $filePath;
        
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            if ($content) {
                $this->users = unserialize($content) ?: [];
            }
        }
    }

    public function getUserById(UserId $id): ?User
    {
        return $this->users[$id->getValue()] ?? null;
    }

    public function save(User $user): void
    {
        $this->users[$user->getId()->getValue()] = $user;
        file_put_contents($this->filePath, serialize($this->users));
    }
}

<?php
declare(strict_types=1);

namespace App\User\Infrastructure;

use App\User\Domain\User;
use App\User\Domain\UserId;
use App\User\Domain\UserRepository;
use Doctrine\ORM\EntityManager;

class DoctrineUserRepository implements UserRepository
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getUserById(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}

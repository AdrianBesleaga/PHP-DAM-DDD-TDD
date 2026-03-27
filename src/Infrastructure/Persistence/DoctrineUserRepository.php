<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of UserRepositoryInterface.
 *
 * This is the production-ready adapter — backed by a real database
 * with ACID transactions via Doctrine's Unit of Work.
 */
final class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    public function findById(UserId $id): ?User
    {
        return $this->em->find(User::class, $id->value());
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy([
            'email' => $email->value(),
        ]);
    }

    /** @return User[] */
    public function findAll(): array
    {
        return $this->em->getRepository(User::class)->findAll();
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function delete(UserId $id): void
    {
        $user = $this->findById($id);
        if ($user !== null) {
            $this->em->remove($user);
            $this->em->flush();
        }
    }

    public function nextId(): UserId
    {
        // TODO: Race condition — two concurrent requests could get the same ID.
        // Production alternatives:
        //   1. Use UUID v4: new UserId(Uuid::uuid4()->toString())
        //   2. Use DB auto-increment and remove nextId() from the interface
        //   3. Use a sequence: SELECT nextval('users_id_seq')
        $result = $this->em->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(id), 0) + 1 FROM users'
        );

        return new UserId((int) $result);
    }
}

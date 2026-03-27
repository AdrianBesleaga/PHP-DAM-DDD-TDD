<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\CreateUserDTO;
use App\Application\DTO\UpdateUserDTO;
use App\Domain\Entity\User;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;

/**
 * Application Service — orchestrates use cases by coordinating
 * between the Domain layer and Infrastructure.
 *
 * Key DDD principles demonstrated:
 * - This service is THIN — it delegates to the Domain Entity for business rules
 * - It depends on the Repository INTERFACE, not a concrete class (DIP)
 * - It translates between DTOs (Application) and Entities (Domain)
 *
 * For Java devs: this is like a @Service class in Spring Boot.
 */
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $repository
    ) {}

    /**
     * Use Case: Get a user's profile by ID.
     *
     * @throws UserNotFoundException
     */
    public function getUserById(int $id): User
    {
        $userId = new UserId($id);
        $user = $this->repository->findById($userId);

        if ($user === null) {
            throw UserNotFoundException::withId($id);
        }

        return $user;
    }

    /**
     * Use Case: List all users.
     *
     * @return User[]
     */
    public function listUsers(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Use Case: Create a new user.
     *
     * @throws DuplicateEmailException
     * @throws \InvalidArgumentException
     */
    public function createUser(CreateUserDTO $dto): User
    {
        // Business rule: email must be unique
        $email = new Email($dto->email);
        $existingUser = $this->repository->findByEmail($email);

        if ($existingUser !== null) {
            throw DuplicateEmailException::withEmail($dto->email);
        }

        $user = new User(
            id: $this->repository->nextId(),
            name: $dto->name,
            email: $email,
        );

        $this->repository->save($user);

        return $user;
    }

    /**
     * Use Case: Update an existing user.
     *
     * @throws UserNotFoundException
     * @throws DuplicateEmailException
     */
    public function updateUser(int $id, UpdateUserDTO $dto): User
    {
        $user = $this->getUserById($id);

        if ($dto->name !== null) {
            $user->rename($dto->name);
        }

        if ($dto->email !== null) {
            $newEmail = new Email($dto->email);

            // Check uniqueness only if email actually changed
            if (!$user->email()->equals($newEmail)) {
                $existingUser = $this->repository->findByEmail($newEmail);
                if ($existingUser !== null) {
                    throw DuplicateEmailException::withEmail($dto->email);
                }
            }

            $user->changeEmail($newEmail);
        }

        $this->repository->save($user);

        return $user;
    }

    /**
     * Use Case: Suspend a user.
     *
     * @throws UserNotFoundException
     * @throws \DomainException if user can't be suspended
     */
    public function suspendUser(int $id): User
    {
        $user = $this->getUserById($id);
        $user->suspend(); // Domain entity enforces the business rule
        $this->repository->save($user);

        return $user;
    }

    /**
     * Use Case: Reactivate a user.
     *
     * @throws UserNotFoundException
     * @throws \DomainException if user is already active
     */
    public function reactivateUser(int $id): User
    {
        $user = $this->getUserById($id);
        $user->reactivate();
        $this->repository->save($user);

        return $user;
    }

    /**
     * Use Case: Delete a user.
     *
     * @throws UserNotFoundException
     */
    public function deleteUser(int $id): void
    {
        $user = $this->getUserById($id);
        $this->repository->delete($user->id());
    }
}

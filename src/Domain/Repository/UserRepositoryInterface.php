<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;

/**
 * Port (Interface) for the User Repository.
 *
 * This is the "Driven Port" in Hexagonal Architecture.
 * The Domain defines WHAT it needs; the Infrastructure provides HOW.
 *
 * Key points for Java developers:
 * - This is exactly like a Spring Data Repository interface
 * - The Domain layer owns this interface (Dependency Inversion)
 * - Infrastructure provides the concrete implementation
 */
interface UserRepositoryInterface
{
    /**
     * Find a user by their unique ID.
     * Returns null if not found (Null Object pattern alternative).
     */
    public function findById(UserId $id): ?User;

    /**
     * Find a user by their email address.
     */
    public function findByEmail(Email $email): ?User;

    /**
     * Retrieve all users.
     * @return User[]
     */
    public function findAll(): array;

    /**
     * Persist a new or updated user.
     */
    public function save(User $user): void;

    /**
     * Remove a user from the repository.
     */
    public function delete(UserId $id): void;

    /**
     * Generate the next available ID.
     * In a real DB this would be auto-increment or UUID generation.
     */
    public function nextId(): UserId;
}

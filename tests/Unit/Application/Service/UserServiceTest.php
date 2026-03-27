<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service;

use App\Application\DTO\CreateUserDTO;
use App\Application\DTO\UpdateUserDTO;
use App\Application\Service\UserService;
use App\Domain\Entity\User;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\UserStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UserService.
 * 
 * KEY INTERVIEW POINT: Because UserService depends on UserRepositoryInterface,
 * we can mock that interface and test the service in COMPLETE ISOLATION
 * from any database or infrastructure.
 * 
 * This is the payoff of Dependency Inversion.
 */
#[CoversClass(UserService::class)]
final class UserServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $repository;
    private UserService $service;

    protected function setUp(): void
    {
        // Create a mock of the Interface — not a concrete class
        $this->repository = $this->createMock(UserRepositoryInterface::class);
        $this->service = new UserService($this->repository);
    }

    // ─── getUserById ─────────────────────────────────────────────────

    #[Test]
    public function it_returns_user_when_found(): void
    {
        // Arrange
        $expectedUser = new User(
            id: new UserId(1),
            name: 'Alice',
            email: new Email('alice@example.com'),
        );

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($this->callback(fn(UserId $id) => $id->value() === 1))
            ->willReturn($expectedUser);

        // Act
        $user = $this->service->getUserById(1);

        // Assert
        $this->assertSame('Alice', $user->name());
        $this->assertSame(1, $user->id()->value());
    }

    #[Test]
    public function it_throws_when_user_not_found(): void
    {
        // Arrange
        $this->repository
            ->method('findById')
            ->willReturn(null);

        // Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('User with ID 999');

        // Act
        $this->service->getUserById(999);
    }

    // ─── listUsers ───────────────────────────────────────────────────

    #[Test]
    public function it_returns_all_users(): void
    {
        // Arrange
        $users = [
            new User(new UserId(1), 'Alice', new Email('alice@example.com')),
            new User(new UserId(2), 'Bob', new Email('bob@example.com')),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($users);

        // Act
        $result = $this->service->listUsers();

        // Assert
        $this->assertCount(2, $result);
    }

    // ─── createUser ──────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_new_user(): void
    {
        // Arrange
        $dto = new CreateUserDTO(name: 'Charlie', email: 'charlie@example.com');

        $this->repository
            ->method('findByEmail')
            ->willReturn(null); // No duplicate

        $this->repository
            ->method('nextId')
            ->willReturn(new UserId(3));

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(User::class));

        // Act
        $user = $this->service->createUser($dto);

        // Assert
        $this->assertSame(3, $user->id()->value());
        $this->assertSame('Charlie', $user->name());
        $this->assertSame('charlie@example.com', $user->email()->value());
        $this->assertSame(UserStatus::Active, $user->status());
    }

    #[Test]
    public function it_rejects_duplicate_email_on_create(): void
    {
        // Arrange
        $dto = new CreateUserDTO(name: 'Duplicate', email: 'alice@example.com');

        $existingUser = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
        );

        $this->repository
            ->method('findByEmail')
            ->willReturn($existingUser);

        // Assert
        $this->expectException(DuplicateEmailException::class);
        $this->expectExceptionMessage('alice@example.com');

        // Act
        $this->service->createUser($dto);
    }

    // ─── updateUser ──────────────────────────────────────────────────

    #[Test]
    public function it_updates_user_name(): void
    {
        // Arrange
        $existingUser = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
        );

        $this->repository
            ->method('findById')
            ->willReturn($existingUser);

        $this->repository
            ->expects($this->once())
            ->method('save');

        $dto = new UpdateUserDTO(name: 'Alice Updated');

        // Act
        $user = $this->service->updateUser(1, $dto);

        // Assert
        $this->assertSame('Alice Updated', $user->name());
    }

    #[Test]
    public function it_updates_user_email_with_uniqueness_check(): void
    {
        // Arrange
        $existingUser = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
        );

        $this->repository
            ->method('findById')
            ->willReturn($existingUser);

        $this->repository
            ->method('findByEmail')
            ->willReturn(null); // New email is available

        $this->repository
            ->expects($this->once())
            ->method('save');

        $dto = new UpdateUserDTO(email: 'newalice@example.com');

        // Act
        $user = $this->service->updateUser(1, $dto);

        // Assert
        $this->assertSame('newalice@example.com', $user->email()->value());
    }

    #[Test]
    public function it_rejects_duplicate_email_on_update(): void
    {
        // Arrange
        $existingUser = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
        );

        $otherUser = new User(
            new UserId(2), 'Bob', new Email('bob@example.com'),
        );

        $this->repository
            ->method('findById')
            ->willReturn($existingUser);

        $this->repository
            ->method('findByEmail')
            ->willReturn($otherUser); // Email is taken

        $this->expectException(DuplicateEmailException::class);

        $dto = new UpdateUserDTO(email: 'bob@example.com');

        // Act
        $this->service->updateUser(1, $dto);
    }

    // ─── suspendUser ─────────────────────────────────────────────────

    #[Test]
    public function it_suspends_an_active_user(): void
    {
        // Arrange
        $user = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
            status: UserStatus::Active,
        );

        $this->repository->method('findById')->willReturn($user);
        $this->repository->expects($this->once())->method('save');

        // Act
        $result = $this->service->suspendUser(1);

        // Assert
        $this->assertSame(UserStatus::Suspended, $result->status());
    }

    #[Test]
    public function it_throws_when_suspending_non_active_user(): void
    {
        // Arrange
        $user = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
            status: UserStatus::Inactive,
        );

        $this->repository->method('findById')->willReturn($user);

        $this->expectException(\DomainException::class);

        // Act
        $this->service->suspendUser(1);
    }

    // ─── reactivateUser ──────────────────────────────────────────────

    #[Test]
    public function it_reactivates_a_suspended_user(): void
    {
        // Arrange
        $user = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
            status: UserStatus::Suspended,
        );

        $this->repository->method('findById')->willReturn($user);
        $this->repository->expects($this->once())->method('save');

        // Act
        $result = $this->service->reactivateUser(1);

        // Assert
        $this->assertSame(UserStatus::Active, $result->status());
    }

    // ─── deleteUser ──────────────────────────────────────────────────

    #[Test]
    public function it_deletes_an_existing_user(): void
    {
        // Arrange
        $user = new User(
            new UserId(1), 'Alice', new Email('alice@example.com'),
        );

        $this->repository->method('findById')->willReturn($user);
        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($this->callback(fn(UserId $id) => $id->value() === 1));

        // Act
        $this->service->deleteUser(1);
    }

    #[Test]
    public function it_throws_when_deleting_non_existent_user(): void
    {
        // Arrange
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        // Act
        $this->service->deleteUser(999);
    }
}

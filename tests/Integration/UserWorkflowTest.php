<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\DTO\CreateUserDTO;
use App\Application\Service\UserService;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\ValueObject\UserStatus;
use App\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration test — wires the REAL InMemoryUserRepository
 * to the UserService, testing the full stack without mocks.
 *
 * This proves the layers work together correctly.
 */
#[CoversClass(UserService::class)]
#[CoversClass(InMemoryUserRepository::class)]
final class UserWorkflowTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        // Use the REAL repository — no mocks
        $repository = new InMemoryUserRepository();
        $this->service = new UserService($repository);
    }

    #[Test]
    public function full_user_lifecycle(): void
    {
        // 1. Create a user
        $user = $this->service->createUser(
            new CreateUserDTO(name: 'Alice', email: 'alice@example.com')
        );

        $this->assertSame(1, $user->id()->value());
        $this->assertSame('Alice', $user->name());
        $this->assertSame(UserStatus::Active, $user->status());

        // 2. Retrieve the user
        $found = $this->service->getUserById(1);
        $this->assertSame('Alice', $found->name());

        // 3. List all users
        $allUsers = $this->service->listUsers();
        $this->assertCount(1, $allUsers);

        // 4. Suspend the user
        $suspended = $this->service->suspendUser(1);
        $this->assertSame(UserStatus::Suspended, $suspended->status());
        $this->assertFalse($suspended->canLogin());

        // 5. Reactivate the user
        $reactivated = $this->service->reactivateUser(1);
        $this->assertSame(UserStatus::Active, $reactivated->status());
        $this->assertTrue($reactivated->canLogin());

        // 6. Delete the user
        $this->service->deleteUser(1);

        // 7. Verify deletion
        $this->expectException(UserNotFoundException::class);
        $this->service->getUserById(1);
    }

    #[Test]
    public function it_prevents_duplicate_emails_across_users(): void
    {
        // Create first user
        $this->service->createUser(
            new CreateUserDTO(name: 'Alice', email: 'shared@example.com')
        );

        // Attempt duplicate
        $this->expectException(DuplicateEmailException::class);

        $this->service->createUser(
            new CreateUserDTO(name: 'Bob', email: 'shared@example.com')
        );
    }

    #[Test]
    public function it_assigns_sequential_ids(): void
    {
        $user1 = $this->service->createUser(
            new CreateUserDTO(name: 'User One', email: 'one@example.com')
        );
        $user2 = $this->service->createUser(
            new CreateUserDTO(name: 'User Two', email: 'two@example.com')
        );

        $this->assertSame(1, $user1->id()->value());
        $this->assertSame(2, $user2->id()->value());
    }
}

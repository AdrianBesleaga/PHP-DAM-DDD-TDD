<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\UserStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    // ─── Factory Helper (SUT Factory pattern) ────────────────────────

    private function createUser(
        int $id = 1,
        string $name = 'Alice',
        string $email = 'alice@example.com',
        UserStatus $status = UserStatus::Active,
    ): User {
        return new User(
            id: new UserId($id),
            name: $name,
            email: new Email($email),
            status: $status,
        );
    }

    // ─── Construction ────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_user_with_valid_data(): void
    {
        $user = $this->createUser();

        $this->assertSame(1, $user->id()->value());
        $this->assertSame('Alice', $user->name());
        $this->assertSame('alice@example.com', $user->email()->value());
        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertTrue($user->canLogin());
        $this->assertNull($user->updatedAt());
    }

    #[Test]
    public function it_defaults_to_active_status(): void
    {
        $user = new User(
            id: new UserId(1),
            name: 'Test',
            email: new Email('test@example.com'),
        );

        $this->assertSame(UserStatus::Active, $user->status());
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot be empty');

        $this->createUser(name: '');
    }

    #[Test]
    public function it_rejects_whitespace_only_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createUser(name: '   ');
    }

    // ─── Rename ──────────────────────────────────────────────────────

    #[Test]
    public function it_can_be_renamed(): void
    {
        $user = $this->createUser();

        $user->rename('Bob');

        $this->assertSame('Bob', $user->name());
        $this->assertNotNull($user->updatedAt());
    }

    #[Test]
    public function rename_rejects_empty_name(): void
    {
        $user = $this->createUser();

        $this->expectException(\InvalidArgumentException::class);

        $user->rename('');
    }

    // ─── Email Change ────────────────────────────────────────────────

    #[Test]
    public function it_can_change_email(): void
    {
        $user = $this->createUser();
        $newEmail = new Email('newalice@example.com');

        $user->changeEmail($newEmail);

        $this->assertSame('newalice@example.com', $user->email()->value());
        $this->assertNotNull($user->updatedAt());
    }

    #[Test]
    public function changing_to_same_email_is_idempotent(): void
    {
        $user = $this->createUser();
        $sameEmail = new Email('alice@example.com');

        $user->changeEmail($sameEmail);

        // updatedAt should remain null because nothing actually changed
        $this->assertNull($user->updatedAt());
    }

    // ─── Suspend ─────────────────────────────────────────────────────

    #[Test]
    public function active_user_can_be_suspended(): void
    {
        $user = $this->createUser(status: UserStatus::Active);

        $user->suspend();

        $this->assertSame(UserStatus::Suspended, $user->status());
        $this->assertFalse($user->canLogin());
    }

    #[Test]
    public function inactive_user_cannot_be_suspended(): void
    {
        $user = $this->createUser(status: UserStatus::Inactive);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot suspend');

        $user->suspend();
    }

    #[Test]
    public function already_suspended_user_cannot_be_suspended_again(): void
    {
        $user = $this->createUser(status: UserStatus::Suspended);

        $this->expectException(\DomainException::class);

        $user->suspend();
    }

    // ─── Reactivate ──────────────────────────────────────────────────

    #[Test]
    public function suspended_user_can_be_reactivated(): void
    {
        $user = $this->createUser(status: UserStatus::Suspended);

        $user->reactivate();

        $this->assertSame(UserStatus::Active, $user->status());
        $this->assertTrue($user->canLogin());
    }

    #[Test]
    public function inactive_user_can_be_reactivated(): void
    {
        $user = $this->createUser(status: UserStatus::Inactive);

        $user->reactivate();

        $this->assertSame(UserStatus::Active, $user->status());
    }

    #[Test]
    public function active_user_cannot_be_reactivated(): void
    {
        $user = $this->createUser(status: UserStatus::Active);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already active');

        $user->reactivate();
    }

    // ─── Serialization ───────────────────────────────────────────────

    #[Test]
    public function it_serializes_to_array(): void
    {
        $user = $this->createUser();

        $data = $user->toArray();

        $this->assertSame(1, $data['id']);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame('alice@example.com', $data['email']);
        $this->assertSame('active', $data['status']);
        $this->assertTrue($data['can_login']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertNull($data['updated_at']);
    }
}

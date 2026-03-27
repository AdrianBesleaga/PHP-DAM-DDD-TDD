<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\UserStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserStatus::class)]
final class UserStatusTest extends TestCase
{
    #[Test]
    public function only_active_users_can_login(): void
    {
        $this->assertTrue(UserStatus::Active->isAllowedToLogin());
        $this->assertFalse(UserStatus::Inactive->isAllowedToLogin());
        $this->assertFalse(UserStatus::Suspended->isAllowedToLogin());
    }

    #[Test]
    public function it_provides_human_readable_labels(): void
    {
        $this->assertSame('Active', UserStatus::Active->label());
        $this->assertSame('Inactive', UserStatus::Inactive->label());
        $this->assertSame('Suspended', UserStatus::Suspended->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $status = UserStatus::from('active');

        $this->assertSame(UserStatus::Active, $status);
    }

    #[Test]
    public function it_throws_on_invalid_string(): void
    {
        $this->expectException(\ValueError::class);

        UserStatus::from('deleted');
    }
}

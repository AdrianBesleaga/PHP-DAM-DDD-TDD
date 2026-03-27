<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserId::class)]
final class UserIdTest extends TestCase
{
    #[Test]
    public function it_creates_a_valid_user_id(): void
    {
        $id = new UserId(42);

        $this->assertSame(42, $id->value());
        $this->assertSame('42', (string) $id);
    }

    #[Test]
    public function it_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('positive integer');

        new UserId(0);
    }

    #[Test]
    public function it_rejects_negative_numbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserId(-5);
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        $id1 = new UserId(1);
        $id2 = new UserId(1);
        $id3 = new UserId(2);

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}

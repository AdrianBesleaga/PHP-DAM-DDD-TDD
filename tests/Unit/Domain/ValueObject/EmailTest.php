<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Email::class)]
final class EmailTest extends TestCase
{
    #[Test]
    public function it_creates_a_valid_email(): void
    {
        $email = new Email('alice@example.com');

        $this->assertSame('alice@example.com', $email->value());
        $this->assertSame('alice@example.com', (string) $email);
    }

    #[Test]
    public function it_extracts_domain(): void
    {
        $email = new Email('user@company.co.uk');

        $this->assertSame('company.co.uk', $email->domain());
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function it_rejects_invalid_emails(string $invalidEmail): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        new Email($invalidEmail);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'empty string' => [''],
            'no at sign' => ['alice-example.com'],
            'no domain' => ['alice@'],
            'no local part' => ['@example.com'],
            'spaces' => ['alice @example.com'],
        ];
    }

    #[Test]
    public function it_compares_case_insensitively(): void
    {
        $email1 = new Email('Alice@Example.COM');
        $email2 = new Email('alice@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    #[Test]
    public function it_detects_different_emails(): void
    {
        $email1 = new Email('alice@example.com');
        $email2 = new Email('bob@example.com');

        $this->assertFalse($email1->equals($email2));
    }
}

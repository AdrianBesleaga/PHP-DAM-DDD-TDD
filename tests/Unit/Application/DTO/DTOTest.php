<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTO;

use App\Application\DTO\CreateUserDTO;
use App\Application\DTO\UpdateUserDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateUserDTO::class)]
#[CoversClass(UpdateUserDTO::class)]
final class DTOTest extends TestCase
{
    // ─── CreateUserDTO ───────────────────────────────────────────────

    #[Test]
    public function create_dto_from_valid_array(): void
    {
        $dto = CreateUserDTO::fromArray([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
    }

    #[Test]
    public function create_dto_trims_whitespace(): void
    {
        $dto = CreateUserDTO::fromArray([
            'name' => '  Alice  ',
            'email' => '  alice@example.com  ',
        ]);

        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
    }

    #[Test]
    public function create_dto_rejects_missing_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"name" is required');

        CreateUserDTO::fromArray(['email' => 'alice@example.com']);
    }

    #[Test]
    public function create_dto_rejects_missing_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"email" is required');

        CreateUserDTO::fromArray(['name' => 'Alice']);
    }

    // ─── UpdateUserDTO ───────────────────────────────────────────────

    #[Test]
    public function update_dto_supports_partial_updates(): void
    {
        $nameOnly = UpdateUserDTO::fromArray(['name' => 'New Name']);

        $this->assertSame('New Name', $nameOnly->name);
        $this->assertNull($nameOnly->email);
        $this->assertTrue($nameOnly->hasChanges());
    }

    #[Test]
    public function update_dto_detects_no_changes(): void
    {
        $empty = UpdateUserDTO::fromArray([]);

        $this->assertFalse($empty->hasChanges());
    }
}

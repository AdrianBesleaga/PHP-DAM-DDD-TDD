<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Folder;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Folder::class)]
final class FolderTest extends TestCase
{
    private function createFolder(
        int $id = 1,
        string $name = 'Marketing',
        int $createdBy = 1,
        ?int $parentId = null,
    ): Folder {
        return new Folder(
            id: new FolderId($id),
            name: $name,
            createdBy: new UserId($createdBy),
            parentId: $parentId !== null ? new FolderId($parentId) : null,
        );
    }

    // ─── Construction ────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_root_folder(): void
    {
        $folder = $this->createFolder();

        $this->assertSame(1, $folder->id()->value());
        $this->assertSame('Marketing', $folder->name());
        $this->assertTrue($folder->isRoot());
        $this->assertNull($folder->parentId());
    }

    #[Test]
    public function it_creates_a_child_folder(): void
    {
        $folder = $this->createFolder(id: 2, parentId: 1);

        $this->assertFalse($folder->isRoot());
        $this->assertSame(1, $folder->parentId()->value());
    }

    #[Test]
    public function it_trims_and_validates_name(): void
    {
        $folder = $this->createFolder(name: '  Trimmed  ');
        $this->assertSame('Trimmed', $folder->name());
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createFolder(name: '');
    }

    #[Test]
    public function it_rejects_self_as_parent(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cannot be its own parent');

        $this->createFolder(id: 5, parentId: 5);
    }

    // ─── Rename ──────────────────────────────────────────────────────

    #[Test]
    public function it_can_be_renamed(): void
    {
        $folder = $this->createFolder();

        $folder->rename('Sales');

        $this->assertSame('Sales', $folder->name());
        $this->assertNotNull($folder->updatedAt());
    }

    #[Test]
    public function rename_rejects_empty_name(): void
    {
        $folder = $this->createFolder();

        $this->expectException(\InvalidArgumentException::class);

        $folder->rename('');
    }

    // ─── Move ────────────────────────────────────────────────────────

    #[Test]
    public function it_can_be_moved_to_a_parent(): void
    {
        $folder = $this->createFolder();

        $folder->moveTo(new FolderId(10));

        $this->assertSame(10, $folder->parentId()->value());
        $this->assertFalse($folder->isRoot());
    }

    #[Test]
    public function it_can_be_moved_to_root(): void
    {
        $folder = $this->createFolder(parentId: 5);

        $folder->moveTo(null);

        $this->assertTrue($folder->isRoot());
        $this->assertNull($folder->parentId());
    }

    #[Test]
    public function moving_to_same_parent_is_idempotent(): void
    {
        $folder = $this->createFolder(parentId: 5);

        $folder->moveTo(new FolderId(5));

        $this->assertNull($folder->updatedAt());
    }

    #[Test]
    public function moving_root_to_root_is_idempotent(): void
    {
        $folder = $this->createFolder(); // Root

        $folder->moveTo(null);

        $this->assertNull($folder->updatedAt());
    }

    #[Test]
    public function it_prevents_self_nesting_on_move(): void
    {
        $folder = $this->createFolder(id: 5);

        $this->expectException(\DomainException::class);

        $folder->moveTo(new FolderId(5));
    }

    // ─── Serialization ───────────────────────────────────────────────

    #[Test]
    public function it_serializes_to_array(): void
    {
        $folder = $this->createFolder(parentId: 3);

        $data = $folder->toArray();

        $this->assertSame(1, $data['id']);
        $this->assertSame('Marketing', $data['name']);
        $this->assertSame(3, $data['parent_id']);
        $this->assertFalse($data['is_root']);
        $this->assertSame(1, $data['created_by']);
    }
}

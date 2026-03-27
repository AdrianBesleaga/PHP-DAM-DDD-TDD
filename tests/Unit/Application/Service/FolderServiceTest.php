<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service;

use App\Application\DTO\CreateFolderDTO;
use App\Application\Service\FolderService;
use App\Domain\Entity\Folder;
use App\Domain\Exception\FolderNotFoundException;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(FolderService::class)]
final class FolderServiceTest extends TestCase
{
    private FolderRepositoryInterface&MockObject $repository;
    private FolderService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FolderRepositoryInterface::class);
        $this->service = new FolderService($this->repository);
    }

    private function createFolder(int $id = 1, ?int $parentId = null): Folder
    {
        return new Folder(
            id: new FolderId($id),
            name: 'Test Folder',
            createdBy: new UserId(1),
            parentId: $parentId !== null ? new FolderId($parentId) : null,
        );
    }

    // ─── createFolder ────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_root_folder(): void
    {
        $dto = new CreateFolderDTO(name: 'Marketing', createdBy: 1);

        $this->repository->method('nextId')->willReturn(new FolderId(1));
        $this->repository->expects($this->once())->method('save');

        $folder = $this->service->createFolder($dto);

        $this->assertSame('Marketing', $folder->name());
        $this->assertTrue($folder->isRoot());
    }

    #[Test]
    public function it_creates_a_child_folder(): void
    {
        $dto = new CreateFolderDTO(name: 'Sub', createdBy: 1, parentId: 5);
        $parent = $this->createFolder(5);

        $this->repository->method('findById')->willReturn($parent);
        $this->repository->method('nextId')->willReturn(new FolderId(2));
        $this->repository->expects($this->once())->method('save');

        $folder = $this->service->createFolder($dto);

        $this->assertSame(5, $folder->parentId()->value());
    }

    #[Test]
    public function it_rejects_nonexistent_parent(): void
    {
        $dto = new CreateFolderDTO(name: 'Orphan', createdBy: 1, parentId: 999);

        $this->repository->method('findById')->willReturn(null);

        $this->expectException(FolderNotFoundException::class);

        $this->service->createFolder($dto);
    }

    // ─── getFolderById ───────────────────────────────────────────────

    #[Test]
    public function it_returns_folder_when_found(): void
    {
        $folder = $this->createFolder();
        $this->repository->method('findById')->willReturn($folder);

        $result = $this->service->getFolderById(1);

        $this->assertSame(1, $result->id()->value());
    }

    #[Test]
    public function it_throws_when_folder_not_found(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(FolderNotFoundException::class);

        $this->service->getFolderById(999);
    }

    // ─── renameFolder ────────────────────────────────────────────────

    #[Test]
    public function it_renames_a_folder(): void
    {
        $folder = $this->createFolder();
        $this->repository->method('findById')->willReturn($folder);
        $this->repository->expects($this->once())->method('save');

        $result = $this->service->renameFolder(1, 'New Name');

        $this->assertSame('New Name', $result->name());
    }

    // ─── moveFolder ──────────────────────────────────────────────────

    #[Test]
    public function it_moves_folder_to_new_parent(): void
    {
        $folder = $this->createFolder();
        $newParent = $this->createFolder(10);

        $this->repository
            ->method('findById')
            ->willReturnCallback(fn(FolderId $id) => match ($id->value()) {
                1 => $folder,
                10 => $newParent,
                default => null,
            });

        $this->repository->expects($this->once())->method('save');

        $result = $this->service->moveFolder(1, 10);

        $this->assertSame(10, $result->parentId()->value());
    }

    #[Test]
    public function it_moves_folder_to_root(): void
    {
        $folder = $this->createFolder(parentId: 5);
        $this->repository->method('findById')->willReturn($folder);
        $this->repository->expects($this->once())->method('save');

        $result = $this->service->moveFolder(1, null);

        $this->assertTrue($result->isRoot());
    }

    // ─── deleteFolder ────────────────────────────────────────────────

    #[Test]
    public function it_deletes_an_existing_folder(): void
    {
        $folder = $this->createFolder();
        $this->repository->method('findById')->willReturn($folder);
        $this->repository->expects($this->once())->method('delete');

        $this->service->deleteFolder(1);
    }

    #[Test]
    public function it_throws_when_deleting_nonexistent_folder(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(FolderNotFoundException::class);

        $this->service->deleteFolder(999);
    }

    // ─── listRootFolders ─────────────────────────────────────────────

    #[Test]
    public function it_lists_root_folders(): void
    {
        $roots = [$this->createFolder(1), $this->createFolder(2)];
        $this->repository->expects($this->once())->method('findRootFolders')->willReturn($roots);

        $result = $this->service->listRootFolders();

        $this->assertCount(2, $result);
    }

    // ─── listSubfolders ──────────────────────────────────────────────

    #[Test]
    public function it_lists_subfolders(): void
    {
        $parent = $this->createFolder(1);
        $children = [$this->createFolder(2, parentId: 1)];

        $this->repository->method('findById')->willReturn($parent);
        $this->repository->method('findByParent')->willReturn($children);

        $result = $this->service->listSubfolders(1);

        $this->assertCount(1, $result);
    }
}

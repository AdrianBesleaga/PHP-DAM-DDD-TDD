<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Folder;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\ValueObject\FolderId;

/**
 * In-Memory Folder Repository adapter.
 */
final class InMemoryFolderRepository implements FolderRepositoryInterface
{
    /** @var array<int, Folder> */
    private array $folders = [];
    private int $nextId = 1;

    public function findById(FolderId $id): ?Folder
    {
        return $this->folders[$id->value()] ?? null;
    }

    /** @return Folder[] */
    public function findByParent(FolderId $parentId): array
    {
        return array_values(array_filter(
            $this->folders,
            fn(Folder $folder) => $folder->parentId() !== null
                && $folder->parentId()->equals($parentId)
        ));
    }

    /** @return Folder[] */
    public function findRootFolders(): array
    {
        return array_values(array_filter(
            $this->folders,
            fn(Folder $folder) => $folder->isRoot()
        ));
    }

    public function save(Folder $folder): void
    {
        $this->folders[$folder->id()->value()] = $folder;

        if ($folder->id()->value() >= $this->nextId) {
            $this->nextId = $folder->id()->value() + 1;
        }
    }

    public function delete(FolderId $id): void
    {
        unset($this->folders[$id->value()]);
    }

    public function nextId(): FolderId
    {
        return new FolderId($this->nextId++);
    }
}

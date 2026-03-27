<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Folder;
use App\Domain\ValueObject\FolderId;

/**
 * Port (Interface) for the Folder Repository.
 */
interface FolderRepositoryInterface
{
    public function findById(FolderId $id): ?Folder;

    /**
     * @return Folder[]
     */
    public function findByParent(FolderId $parentId): array;

    /**
     * @return Folder[]
     */
    public function findRootFolders(): array;

    public function save(Folder $folder): void;

    public function delete(FolderId $id): void;

    public function nextId(): FolderId;
}

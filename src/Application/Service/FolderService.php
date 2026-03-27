<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\CreateFolderDTO;
use App\Domain\Entity\Folder;
use App\Domain\Exception\FolderNotFoundException;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\UserId;

/**
 * Application Service for Folder use cases.
 */
final class FolderService
{
    public function __construct(
        private readonly FolderRepositoryInterface $repository
    ) {}

    /**
     * Use Case: Create a new folder.
     *
     * @throws FolderNotFoundException if parent doesn't exist
     */
    public function createFolder(CreateFolderDTO $dto): Folder
    {
        // Validate parent exists if specified
        if ($dto->parentId !== null) {
            $parent = $this->repository->findById(new FolderId($dto->parentId));
            if ($parent === null) {
                throw FolderNotFoundException::withId($dto->parentId);
            }
        }

        $folder = new Folder(
            id: $this->repository->nextId(),
            name: $dto->name,
            createdBy: new UserId($dto->createdBy),
            parentId: $dto->parentId !== null ? new FolderId($dto->parentId) : null,
        );

        $this->repository->save($folder);

        return $folder;
    }

    /**
     * Use Case: Get a folder by ID.
     *
     * @throws FolderNotFoundException
     */
    public function getFolderById(int $id): Folder
    {
        $folder = $this->repository->findById(new FolderId($id));

        if ($folder === null) {
            throw FolderNotFoundException::withId($id);
        }

        return $folder;
    }

    /**
     * Use Case: List root folders (no parent).
     *
     * @return Folder[]
     */
    public function listRootFolders(): array
    {
        return $this->repository->findRootFolders();
    }

    /**
     * Use Case: List subfolders of a parent.
     *
     * @return Folder[]
     * @throws FolderNotFoundException
     */
    public function listSubfolders(int $parentId): array
    {
        $this->getFolderById($parentId); // Verify parent exists

        return $this->repository->findByParent(new FolderId($parentId));
    }

    /**
     * Use Case: Rename a folder.
     *
     * @throws FolderNotFoundException
     */
    public function renameFolder(int $id, string $newName): Folder
    {
        $folder = $this->getFolderById($id);
        $folder->rename($newName);
        $this->repository->save($folder);

        return $folder;
    }

    /**
     * Use Case: Move a folder to a new parent.
     *
     * @throws FolderNotFoundException
     * @throws \DomainException if self-nesting
     */
    public function moveFolder(int $id, ?int $newParentId): Folder
    {
        $folder = $this->getFolderById($id);

        // Validate new parent exists if specified
        if ($newParentId !== null) {
            $parent = $this->repository->findById(new FolderId($newParentId));
            if ($parent === null) {
                throw FolderNotFoundException::withId($newParentId);
            }
        }

        $folder->moveTo($newParentId !== null ? new FolderId($newParentId) : null);
        $this->repository->save($folder);

        return $folder;
    }

    /**
     * Use Case: Delete a folder.
     *
     * @throws FolderNotFoundException
     */
    public function deleteFolder(int $id): void
    {
        $folder = $this->getFolderById($id);
        $this->repository->delete($folder->id());
    }
}

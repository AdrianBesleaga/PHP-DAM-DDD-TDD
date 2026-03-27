<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\UploadAssetDTO;
use App\Domain\Entity\Asset;
use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Exception\AssetNotFoundException;
use App\Domain\Exception\FolderNotFoundException;
use App\Domain\Repository\AssetRepositoryInterface;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\FileName;
use App\Domain\ValueObject\FileSize;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\MimeType;
use App\Domain\ValueObject\UserId;

/**
 * Application Service for Asset use cases.
 *
 * Orchestrates between the Domain layer and Infrastructure.
 * Thin service — all business rules delegate to the Asset entity.
 */
final class AssetService
{
    public function __construct(
        private readonly AssetRepositoryInterface $assetRepository,
        private readonly FolderRepositoryInterface $folderRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Use Case: Upload a new asset.
     *
     * @throws FolderNotFoundException if target folder doesn't exist
     * @throws \InvalidArgumentException if file data is invalid
     */
    public function uploadAsset(UploadAssetDTO $dto): Asset
    {
        // Validate folder exists if specified
        if ($dto->folderId !== null) {
            $folder = $this->folderRepository->findById(new FolderId($dto->folderId));
            if ($folder === null) {
                throw FolderNotFoundException::withId($dto->folderId);
            }
        }

        $asset = new Asset(
            id: $this->assetRepository->nextId(),
            fileName: new FileName($dto->fileName),
            fileSize: new FileSize($dto->fileSize),
            mimeType: new MimeType($dto->mimeType),
            uploadedBy: new UserId($dto->uploadedBy),
            folderId: $dto->folderId !== null ? new FolderId($dto->folderId) : null,
            description: $dto->description,
            tags: $dto->tags,
        );

        $this->assetRepository->save($asset);
        $this->eventDispatcher->dispatch($asset->pullDomainEvents());

        return $asset;
    }

    /**
     * Use Case: Get a single asset by ID.
     *
     * @throws AssetNotFoundException
     */
    public function getAssetById(int $id): Asset
    {
        $asset = $this->assetRepository->findById(new AssetId($id));

        if ($asset === null) {
            throw AssetNotFoundException::withId($id);
        }

        return $asset;
    }

    /**
     * Use Case: List all assets.
     *
     * @return Asset[]
     */
    public function listAssets(): array
    {
        return $this->assetRepository->findAll();
    }

    /**
     * Use Case: List assets in a folder.
     *
     * @return Asset[]
     * @throws FolderNotFoundException
     */
    public function listAssetsByFolder(int $folderId): array
    {
        $folder = $this->folderRepository->findById(new FolderId($folderId));
        if ($folder === null) {
            throw FolderNotFoundException::withId($folderId);
        }

        return $this->assetRepository->findByFolder(new FolderId($folderId));
    }

    /**
     * Use Case: Publish an asset.
     *
     * @throws AssetNotFoundException
     * @throws \DomainException if business rules are not met
     */
    public function publishAsset(int $id): Asset
    {
        $asset = $this->getAssetById($id);
        $asset->publish();
        $this->assetRepository->save($asset);
        $this->eventDispatcher->dispatch($asset->pullDomainEvents());

        return $asset;
    }

    /**
     * Use Case: Archive an asset.
     *
     * @throws AssetNotFoundException
     */
    public function archiveAsset(int $id): Asset
    {
        $asset = $this->getAssetById($id);
        $asset->archive();
        $this->assetRepository->save($asset);
        $this->eventDispatcher->dispatch($asset->pullDomainEvents());

        return $asset;
    }

    /**
     * Use Case: Restore an archived asset to draft.
     *
     * @throws AssetNotFoundException
     */
    public function restoreAsset(int $id): Asset
    {
        $asset = $this->getAssetById($id);
        $asset->restoreToDraft();
        $this->assetRepository->save($asset);

        return $asset;
    }

    /**
     * Use Case: Move an asset to a folder.
     *
     * @throws AssetNotFoundException
     * @throws FolderNotFoundException
     */
    public function moveAsset(int $assetId, int $folderId): Asset
    {
        $asset = $this->getAssetById($assetId);

        $folder = $this->folderRepository->findById(new FolderId($folderId));
        if ($folder === null) {
            throw FolderNotFoundException::withId($folderId);
        }

        $asset->moveTo(new FolderId($folderId));
        $this->assetRepository->save($asset);

        return $asset;
    }

    /**
     * Use Case: Add a tag to an asset.
     *
     * @throws AssetNotFoundException
     */
    public function tagAsset(int $id, string $tag): Asset
    {
        $asset = $this->getAssetById($id);
        $asset->addTag($tag);
        $this->assetRepository->save($asset);

        return $asset;
    }

    /**
     * Use Case: Remove a tag from an asset.
     *
     * @throws AssetNotFoundException
     */
    public function untagAsset(int $id, string $tag): Asset
    {
        $asset = $this->getAssetById($id);
        $asset->removeTag($tag);
        $this->assetRepository->save($asset);

        return $asset;
    }

    /**
     * Use Case: Delete an asset.
     *
     * @throws AssetNotFoundException
     */
    public function deleteAsset(int $id): void
    {
        $asset = $this->getAssetById($id);
        $this->assetRepository->delete($asset->id());
    }
}

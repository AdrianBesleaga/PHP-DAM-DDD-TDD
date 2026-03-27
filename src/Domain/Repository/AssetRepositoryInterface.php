<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Asset;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\FolderId;

/**
 * Port (Interface) for the Asset Repository.
 */
interface AssetRepositoryInterface
{
    public function findById(AssetId $id): ?Asset;

    /**
     * @return Asset[]
     */
    public function findAll(): array;

    /**
     * @return Asset[]
     */
    public function findByFolder(FolderId $folderId): array;

    /**
     * @return Asset[]
     */
    public function findByTag(string $tag): array;

    public function save(Asset $asset): void;

    public function delete(AssetId $id): void;

    public function nextId(): AssetId;
}

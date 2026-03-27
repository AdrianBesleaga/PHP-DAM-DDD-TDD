<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Asset;
use App\Domain\Repository\AssetRepositoryInterface;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\FolderId;

/**
 * In-Memory Asset Repository adapter.
 */
final class InMemoryAssetRepository implements AssetRepositoryInterface
{
    /** @var array<int, Asset> */
    private array $assets = [];
    private int $nextId = 1;

    public function findById(AssetId $id): ?Asset
    {
        return $this->assets[$id->value()] ?? null;
    }

    /** @return Asset[] */
    public function findAll(): array
    {
        return array_values($this->assets);
    }

    /** @return Asset[] */
    public function findByFolder(FolderId $folderId): array
    {
        return array_values(array_filter(
            $this->assets,
            fn(Asset $asset) => $asset->folderId() !== null
                && $asset->folderId()->equals($folderId)
        ));
    }

    /** @return Asset[] */
    public function findByTag(string $tag): array
    {
        $tag = strtolower(trim($tag));

        return array_values(array_filter(
            $this->assets,
            fn(Asset $asset) => $asset->hasTag($tag)
        ));
    }

    public function save(Asset $asset): void
    {
        $this->assets[$asset->id()->value()] = $asset;

        if ($asset->id()->value() >= $this->nextId) {
            $this->nextId = $asset->id()->value() + 1;
        }
    }

    public function delete(AssetId $id): void
    {
        unset($this->assets[$id->value()]);
    }

    public function nextId(): AssetId
    {
        return new AssetId($this->nextId++);
    }
}

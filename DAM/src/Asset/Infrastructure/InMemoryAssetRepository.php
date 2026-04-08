<?php
declare(strict_types=1);

namespace App\Asset\Infrastructure;

use App\Asset\Domain\Asset;
use App\Asset\Domain\AssetId;
use App\Asset\Domain\AssetRepository;

class InMemoryAssetRepository implements AssetRepository
{
    /** @var array<string, Asset> */
    private array $assets = [];

    public function getById(AssetId $id): ?Asset
    {
        return $this->assets[$id->getValue()] ?? null;
    }

    public function save(Asset $asset): void
    {
        $this->assets[$asset->getId()->getValue()] = $asset;
    }
}

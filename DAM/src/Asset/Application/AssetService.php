<?php
declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Domain\Asset;
use App\Asset\Domain\AssetId;
use App\Asset\Domain\AssetDetails;
use App\Asset\Domain\AssetRepository;
use App\Shared\Domain\TenantId;

class AssetService
{
    private AssetRepository $assetRepository;

    public function __construct(AssetRepository $assetRepository)
    {
        $this->assetRepository = $assetRepository;
    }

    public function uploadAsset(string $name, string $description, string $tenantIdString, int $size, string $type, ?string $imageUrl = null): Asset
    {
        $id = new AssetId(uniqid());
        $tenantId = new TenantId($tenantIdString);
        
        $details = new AssetDetails($imageUrl, $size, $type);
        
        // Use static factory
        $asset = Asset::upload($id, $name, $description, $tenantId, $details);

        $this->assetRepository->save($asset);

        return $asset;
    }

    public function getAssetById(string $assetIdString): ?Asset
    {
        return $this->assetRepository->getById(new AssetId($assetIdString));
    }

    // Keep rest straightforward 
    public function updateAsset(string $assetIdString, string $name, string $description): ?Asset
    {
        $asset = $this->assetRepository->getById(new AssetId($assetIdString));
        if ($asset) {
            $asset->update($name, $description);
            $this->assetRepository->save($asset);
        }
        return $asset;
    }

    public function convertAsset(string $assetIdString, string $newType, int $newSize, ?string $newImageUrl = null): ?Asset
    {
        $asset = $this->assetRepository->getById(new AssetId($assetIdString));
        if ($asset) {
            $asset->convert($newType, $newSize, $newImageUrl);
            $this->assetRepository->save($asset);
        }
        return $asset;
    }

    public function addTagToAsset(string $assetIdString, string $tag): ?Asset
    {
        $asset = $this->assetRepository->getById(new AssetId($assetIdString));
        if ($asset) {
            $asset->addTag($tag);
            $this->assetRepository->save($asset);
        }
        return $asset;
    }

    public function addMetadataToAsset(string $assetIdString, string $key, string $value): ?Asset
    {
        $asset = $this->assetRepository->getById(new AssetId($assetIdString));
        if ($asset) {
            $asset->addMetadata($key, $value);
            $this->assetRepository->save($asset);
        }
        return $asset;
    }
}

<?php
declare(strict_types=1);

namespace App\Asset\Infrastructure;

use App\Asset\Domain\Asset;
use App\Asset\Domain\AssetId;
use App\Asset\Domain\AssetRepository;

class FileAssetRepository implements AssetRepository
{
    private string $filePath;
    /** @var array<string, Asset> */
    private array $assets = [];

    public function __construct(string $filePath = __DIR__ . '/../../../../assets.db')
    {
        $this->filePath = $filePath;
        
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            if ($content) {
                $this->assets = unserialize($content) ?: [];
            }
        }
    }

    public function getById(AssetId $id): ?Asset
    {
        return $this->assets[$id->getValue()] ?? null;
    }

    public function save(Asset $asset): void
    {
        $this->assets[$asset->getId()->getValue()] = $asset;
        file_put_contents($this->filePath, serialize($this->assets));
    }
}

<?php
declare(strict_types=1);

namespace App\Asset\Domain;

interface AssetRepository
{
    public function getById(AssetId $id): ?Asset;
    public function save(Asset $asset): void;
}

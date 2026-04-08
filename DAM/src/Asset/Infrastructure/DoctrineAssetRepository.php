<?php
declare(strict_types=1);

namespace App\Asset\Infrastructure;

use App\Asset\Domain\Asset;
use App\Asset\Domain\AssetId;
use App\Asset\Domain\AssetRepository;
use Doctrine\ORM\EntityManager;

class DoctrineAssetRepository implements AssetRepository
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getById(AssetId $id): ?Asset
    {
        return $this->entityManager->find(Asset::class, $id);
    }

    public function save(Asset $asset): void
    {
        $this->entityManager->persist($asset);
        $this->entityManager->flush();
    }
}

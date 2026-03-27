<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Asset;
use App\Domain\Repository\AssetRepositoryInterface;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\FolderId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of AssetRepositoryInterface.
 */
final class DoctrineAssetRepository implements AssetRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    public function findById(AssetId $id): ?Asset
    {
        return $this->em->find(Asset::class, $id->value());
    }

    /** @return Asset[] */
    public function findAll(): array
    {
        return $this->em->getRepository(Asset::class)->findAll();
    }

    /** @return Asset[] */
    public function findByFolder(FolderId $folderId): array
    {
        return $this->em->getRepository(Asset::class)->findBy([
            'folderId' => $folderId->value(),
        ]);
    }

    /** @return Asset[] */
    public function findByTag(string $tag): array
    {
        // TODO: This LIKE-based JSON search is a pragmatic shortcut for SQLite.
        // Limitations:
        //   - Tags containing '%' would produce overly broad matches
        //   - Depends on json_encode wrapping values in double quotes
        // Production fix: Use a dedicated `asset_tags` junction table:
        //   SELECT a.* FROM assets a
        //   JOIN asset_tags t ON a.id = t.asset_id
        //   WHERE t.tag = :tag
        // Or with MySQL 5.7+: JSON_CONTAINS(a.tags, :tag)
        $tag = strtolower(trim($tag));

        return $this->em->createQueryBuilder()
            ->select('a')
            ->from(Asset::class, 'a')
            ->where('a.tags LIKE :tag')
            ->setParameter('tag', '%"' . $tag . '"%')
            ->getQuery()
            ->getResult();
    }

    public function save(Asset $asset): void
    {
        $this->em->persist($asset);
        $this->em->flush();
    }

    public function delete(AssetId $id): void
    {
        $asset = $this->findById($id);
        if ($asset !== null) {
            $this->em->remove($asset);
            $this->em->flush();
        }
    }

    public function nextId(): AssetId
    {
        // TODO: Race condition under concurrent access — see DoctrineUserRepository::nextId()
        $result = $this->em->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(id), 0) + 1 FROM assets'
        );

        return new AssetId((int) $result);
    }
}

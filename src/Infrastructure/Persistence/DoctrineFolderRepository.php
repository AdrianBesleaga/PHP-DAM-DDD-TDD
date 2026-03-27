<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Folder;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\ValueObject\FolderId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine ORM implementation of FolderRepositoryInterface.
 */
final class DoctrineFolderRepository implements FolderRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}

    public function findById(FolderId $id): ?Folder
    {
        return $this->em->find(Folder::class, $id->value());
    }

    /** @return Folder[] */
    public function findByParent(FolderId $parentId): array
    {
        return $this->em->getRepository(Folder::class)->findBy([
            'parentId' => $parentId->value(),
        ]);
    }

    /** @return Folder[] */
    public function findRootFolders(): array
    {
        return $this->em->getRepository(Folder::class)->findBy([
            'parentId' => null,
        ]);
    }

    public function save(Folder $folder): void
    {
        $this->em->persist($folder);
        $this->em->flush();
    }

    public function delete(FolderId $id): void
    {
        $folder = $this->findById($id);
        if ($folder !== null) {
            $this->em->remove($folder);
            $this->em->flush();
        }
    }

    public function nextId(): FolderId
    {
        // TODO: Race condition under concurrent access — see DoctrineUserRepository::nextId()
        $result = $this->em->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(id), 0) + 1 FROM folders'
        );

        return new FolderId((int) $result);
    }
}

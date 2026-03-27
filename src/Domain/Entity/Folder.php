<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\UserId;

/**
 * Folder Entity — organizes assets in a hierarchy.
 *
 * Supports parent-child relationships with a self-nesting guard:
 * a folder cannot be its own parent.
 */
final class Folder
{
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly FolderId $id,
        private string $name,
        private readonly UserId $createdBy,
        private ?FolderId $parentId = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->name = trim($name);

        if ($this->name === '') {
            throw new \InvalidArgumentException('Folder name cannot be empty');
        }

        $this->validateParent($parentId);
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = null;
    }

    // ─── Accessors ───────────────────────────────────────────────────

    public function id(): FolderId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdBy(): UserId
    {
        return $this->createdBy;
    }

    public function parentId(): ?FolderId
    {
        return $this->parentId;
    }

    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ─── Domain Behavior ─────────────────────────────────────────────

    /**
     * Rename the folder.
     */
    public function rename(string $newName): void
    {
        $newName = trim($newName);

        if ($newName === '') {
            throw new \InvalidArgumentException('Folder name cannot be empty');
        }

        $this->name = $newName;
        $this->touch();
    }

    /**
     * Move folder to a new parent (or to root if null).
     *
     * @throws \DomainException if trying to nest folder inside itself
     */
    public function moveTo(?FolderId $newParentId): void
    {
        $this->validateParent($newParentId);

        // Idempotent — same parent
        if ($this->parentId === null && $newParentId === null) {
            return;
        }
        if ($this->parentId !== null && $newParentId !== null && $this->parentId->equals($newParentId)) {
            return;
        }

        $this->parentId = $newParentId;
        $this->touch();
    }

    // ─── Serialization ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'name' => $this->name,
            'parent_id' => $this->parentId?->value(),
            'is_root' => $this->isRoot(),
            'created_by' => $this->createdBy->value(),
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    // ─── Guards ──────────────────────────────────────────────────────

    private function validateParent(?FolderId $parentId): void
    {
        if ($parentId !== null && $parentId->equals($this->id)) {
            throw new \DomainException('A folder cannot be its own parent');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

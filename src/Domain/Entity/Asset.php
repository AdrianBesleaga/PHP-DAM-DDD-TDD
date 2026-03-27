<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Event\AssetArchived;
use App\Domain\Event\AssetPublished;
use App\Domain\Event\EventRecordingTrait;
use App\Domain\Exception\InvalidAssetTransitionException;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\AssetStatus;
use App\Domain\ValueObject\FileName;
use App\Domain\ValueObject\FileSize;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\MimeType;
use App\Domain\ValueObject\UserId;

/**
 * Asset Entity — the core Aggregate Root of the DAM system.
 *
 * Manages the lifecycle of a digital asset (image, video, document).
 *
 * Key DDD concepts:
 * - Aggregate Root: owns its tags — they are part of this aggregate boundary
 * - Rich Domain Model: lifecycle transitions and business rules live here
 * - State Machine: Draft → Published → Archived with invariant enforcement
 */
final class Asset
{
    use EventRecordingTrait;

    private const int MAX_TAGS = 20;

    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    /** @var string[] */
    private array $tags;

    public function __construct(
        private readonly AssetId $id,
        private FileName $fileName,
        private readonly FileSize $fileSize,
        private readonly MimeType $mimeType,
        private readonly UserId $uploadedBy,
        private AssetStatus $status = AssetStatus::Draft,
        private ?FolderId $folderId = null,
        private ?string $description = null,
        array $tags = [],
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->tags = array_values(array_unique($tags));
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = null;
    }

    // ─── Accessors ───────────────────────────────────────────────────

    public function id(): AssetId
    {
        return $this->id;
    }

    public function fileName(): FileName
    {
        return $this->fileName;
    }

    public function fileSize(): FileSize
    {
        return $this->fileSize;
    }

    public function mimeType(): MimeType
    {
        return $this->mimeType;
    }

    public function uploadedBy(): UserId
    {
        return $this->uploadedBy;
    }

    public function status(): AssetStatus
    {
        return $this->status;
    }

    public function folderId(): ?FolderId
    {
        return $this->folderId;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /** @return string[] */
    public function tags(): array
    {
        return $this->tags;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ─── Lifecycle Transitions ───────────────────────────────────────

    /**
     * Publish the asset.
     *
     * Business rules:
     * - Must be in Draft status
     * - Must have a description (metadata completeness)
     *
     * @throws InvalidAssetTransitionException
     * @throws \DomainException if description missing
     */
    public function publish(): void
    {
        if (!$this->status->canTransitionTo(AssetStatus::Published)) {
            throw InvalidAssetTransitionException::cannotTransition(
                $this->status,
                AssetStatus::Published
            );
        }

        if ($this->description === null || trim($this->description) === '') {
            throw new \DomainException(
                'Cannot publish asset without a description. Add a description first.'
            );
        }

        $this->status = AssetStatus::Published;
        $this->touch();
        $this->recordEvent(new AssetPublished($this->id, $this->fileName->value(), 'user:' . $this->uploadedBy->value()));
    }

    /**
     * Archive the asset. Only published assets can be archived.
     *
     * @throws InvalidAssetTransitionException
     */
    public function archive(): void
    {
        if (!$this->status->canTransitionTo(AssetStatus::Archived)) {
            throw InvalidAssetTransitionException::cannotTransition(
                $this->status,
                AssetStatus::Archived
            );
        }

        $this->status = AssetStatus::Archived;
        $this->touch();
        $this->recordEvent(new AssetArchived($this->id, $this->fileName->value()));
    }

    /**
     * Restore an archived asset back to draft for reworking.
     *
     * @throws InvalidAssetTransitionException
     */
    public function restoreToDraft(): void
    {
        if (!$this->status->canTransitionTo(AssetStatus::Draft)) {
            throw InvalidAssetTransitionException::cannotTransition(
                $this->status,
                AssetStatus::Draft
            );
        }

        $this->status = AssetStatus::Draft;
        $this->touch();
    }

    // ─── Folder Management ───────────────────────────────────────────

    /**
     * Move this asset into a folder.
     */
    public function moveTo(FolderId $folderId): void
    {
        if ($this->folderId !== null && $this->folderId->equals($folderId)) {
            return; // Idempotent — already in this folder
        }

        $this->folderId = $folderId;
        $this->touch();
    }

    /**
     * Remove this asset from its current folder (back to root).
     */
    public function removeFromFolder(): void
    {
        if ($this->folderId === null) {
            return; // Idempotent
        }

        $this->folderId = null;
        $this->touch();
    }

    // ─── Tag Management ──────────────────────────────────────────────

    /**
     * Add a tag. Business rule: max 20 tags per asset.
     *
     * @throws \DomainException if max tags reached
     */
    public function addTag(string $tag): void
    {
        $tag = strtolower(trim($tag));

        if ($tag === '') {
            throw new \InvalidArgumentException('Tag cannot be empty');
        }

        if (in_array($tag, $this->tags, true)) {
            return; // Idempotent — already tagged
        }

        if (count($this->tags) >= self::MAX_TAGS) {
            throw new \DomainException(
                sprintf('Cannot add more than %d tags to an asset', self::MAX_TAGS)
            );
        }

        $this->tags[] = $tag;
        $this->touch();
    }

    /**
     * Remove a tag.
     */
    public function removeTag(string $tag): void
    {
        $tag = strtolower(trim($tag));
        $index = array_search($tag, $this->tags, true);

        if ($index === false) {
            return; // Idempotent — tag not present
        }

        unset($this->tags[$index]);
        $this->tags = array_values($this->tags); // Re-index
        $this->touch();
    }

    public function hasTag(string $tag): bool
    {
        return in_array(strtolower(trim($tag)), $this->tags, true);
    }

    // ─── Metadata ────────────────────────────────────────────────────

    /**
     * Update the asset description.
     */
    public function updateDescription(string $description): void
    {
        $description = trim($description);

        if ($description === '') {
            throw new \InvalidArgumentException('Description cannot be empty');
        }

        $this->description = $description;
        $this->touch();
    }

    /**
     * Rename the asset file.
     */
    public function rename(FileName $newFileName): void
    {
        if ($this->fileName->equals($newFileName)) {
            return; // Idempotent
        }

        $this->fileName = $newFileName;
        $this->touch();
    }

    // ─── Serialization ───────────────────────────────────────────────

    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'file_name' => $this->fileName->value(),
            'file_size' => $this->fileSize->bytes(),
            'file_size_human' => $this->fileSize->toHumanReadable(),
            'mime_type' => $this->mimeType->value(),
            'category' => $this->mimeType->category(),
            'status' => $this->status->value,
            'folder_id' => $this->folderId?->value(),
            'uploaded_by' => $this->uploadedBy->value(),
            'description' => $this->description,
            'tags' => $this->tags,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entity;

use App\Domain\Entity\Asset;
use App\Domain\Exception\InvalidAssetTransitionException;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\AssetStatus;
use App\Domain\ValueObject\FileName;
use App\Domain\ValueObject\FileSize;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\MimeType;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Asset::class)]
final class AssetTest extends TestCase
{
    // ─── SUT Factory ─────────────────────────────────────────────────

    private function createAsset(
        int $id = 1,
        string $fileName = 'photo.jpg',
        int $fileSize = 1024,
        string $mimeType = 'image/jpeg',
        int $uploadedBy = 1,
        AssetStatus $status = AssetStatus::Draft,
        ?int $folderId = null,
        ?string $description = null,
        array $tags = [],
    ): Asset {
        return new Asset(
            id: new AssetId($id),
            fileName: new FileName($fileName),
            fileSize: new FileSize($fileSize),
            mimeType: new MimeType($mimeType),
            uploadedBy: new UserId($uploadedBy),
            status: $status,
            folderId: $folderId !== null ? new FolderId($folderId) : null,
            description: $description,
            tags: $tags,
        );
    }

    // ─── Construction ────────────────────────────────────────────────

    #[Test]
    public function it_creates_asset_with_valid_data(): void
    {
        $asset = $this->createAsset(description: 'A test photo', tags: ['test']);

        $this->assertSame(1, $asset->id()->value());
        $this->assertSame('photo.jpg', $asset->fileName()->value());
        $this->assertSame(1024, $asset->fileSize()->bytes());
        $this->assertSame('image/jpeg', $asset->mimeType()->value());
        $this->assertSame(1, $asset->uploadedBy()->value());
        $this->assertSame(AssetStatus::Draft, $asset->status());
        $this->assertNull($asset->folderId());
        $this->assertSame('A test photo', $asset->description());
        $this->assertSame(['test'], $asset->tags());
        $this->assertNull($asset->updatedAt());
    }

    #[Test]
    public function it_defaults_to_draft_status(): void
    {
        $asset = $this->createAsset();

        $this->assertSame(AssetStatus::Draft, $asset->status());
    }

    #[Test]
    public function it_deduplicates_tags_on_construction(): void
    {
        $asset = $this->createAsset(tags: ['photo', 'photo', 'image']);

        $this->assertSame(['photo', 'image'], $asset->tags());
    }

    // ─── Lifecycle: Publish ──────────────────────────────────────────

    #[Test]
    public function draft_asset_with_description_can_be_published(): void
    {
        $asset = $this->createAsset(description: 'Has a description');

        $asset->publish();

        $this->assertSame(AssetStatus::Published, $asset->status());
        $this->assertNotNull($asset->updatedAt());
    }

    #[Test]
    public function draft_asset_without_description_cannot_be_published(): void
    {
        $asset = $this->createAsset(); // No description

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('description');

        $asset->publish();
    }

    #[Test]
    public function published_asset_cannot_be_published_again(): void
    {
        $asset = $this->createAsset(description: 'Desc');
        $asset->publish();

        $this->expectException(InvalidAssetTransitionException::class);
        $this->expectExceptionMessage('published');

        $asset->publish();
    }

    #[Test]
    public function archived_asset_cannot_be_published(): void
    {
        $asset = $this->createAsset(description: 'Desc');
        $asset->publish();
        $asset->archive();

        $this->expectException(InvalidAssetTransitionException::class);

        $asset->publish();
    }

    // ─── Lifecycle: Archive ──────────────────────────────────────────

    #[Test]
    public function published_asset_can_be_archived(): void
    {
        $asset = $this->createAsset(description: 'Desc');
        $asset->publish();

        $asset->archive();

        $this->assertSame(AssetStatus::Archived, $asset->status());
    }

    #[Test]
    public function draft_asset_cannot_be_archived(): void
    {
        $asset = $this->createAsset();

        $this->expectException(InvalidAssetTransitionException::class);

        $asset->archive();
    }

    // ─── Lifecycle: Restore ──────────────────────────────────────────

    #[Test]
    public function archived_asset_can_be_restored_to_draft(): void
    {
        $asset = $this->createAsset(description: 'Desc');
        $asset->publish();
        $asset->archive();

        $asset->restoreToDraft();

        $this->assertSame(AssetStatus::Draft, $asset->status());
    }

    #[Test]
    public function draft_asset_cannot_be_restored(): void
    {
        $asset = $this->createAsset();

        $this->expectException(InvalidAssetTransitionException::class);

        $asset->restoreToDraft();
    }

    // ─── Full Lifecycle ──────────────────────────────────────────────

    #[Test]
    public function it_supports_full_lifecycle_loop(): void
    {
        $asset = $this->createAsset(description: 'Lifecycle test');

        // Draft → Published
        $asset->publish();
        $this->assertSame(AssetStatus::Published, $asset->status());

        // Published → Archived
        $asset->archive();
        $this->assertSame(AssetStatus::Archived, $asset->status());

        // Archived → Draft (restore)
        $asset->restoreToDraft();
        $this->assertSame(AssetStatus::Draft, $asset->status());

        // Draft → Published again
        $asset->publish();
        $this->assertSame(AssetStatus::Published, $asset->status());
    }

    // ─── Folder Management ───────────────────────────────────────────

    #[Test]
    public function it_can_be_moved_to_a_folder(): void
    {
        $asset = $this->createAsset();

        $asset->moveTo(new FolderId(5));

        $this->assertSame(5, $asset->folderId()->value());
        $this->assertNotNull($asset->updatedAt());
    }

    #[Test]
    public function moving_to_same_folder_is_idempotent(): void
    {
        $asset = $this->createAsset(folderId: 5);

        $asset->moveTo(new FolderId(5));

        $this->assertNull($asset->updatedAt()); // No change
    }

    #[Test]
    public function it_can_be_removed_from_folder(): void
    {
        $asset = $this->createAsset(folderId: 5);

        $asset->removeFromFolder();

        $this->assertNull($asset->folderId());
    }

    #[Test]
    public function removing_from_folder_when_not_in_one_is_idempotent(): void
    {
        $asset = $this->createAsset(); // No folder

        $asset->removeFromFolder();

        $this->assertNull($asset->updatedAt());
    }

    // ─── Tag Management ──────────────────────────────────────────────

    #[Test]
    public function it_can_add_tags(): void
    {
        $asset = $this->createAsset();

        $asset->addTag('landscape');

        $this->assertSame(['landscape'], $asset->tags());
        $this->assertTrue($asset->hasTag('landscape'));
    }

    #[Test]
    public function tags_are_normalized_to_lowercase(): void
    {
        $asset = $this->createAsset();

        $asset->addTag('  LANDSCAPE  ');

        $this->assertSame(['landscape'], $asset->tags());
        $this->assertTrue($asset->hasTag('LANDSCAPE'));
    }

    #[Test]
    public function adding_duplicate_tag_is_idempotent(): void
    {
        $asset = $this->createAsset(tags: ['photo']);

        $asset->addTag('photo');

        $this->assertSame(['photo'], $asset->tags());
    }

    #[Test]
    public function it_rejects_empty_tags(): void
    {
        $asset = $this->createAsset();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag cannot be empty');

        $asset->addTag('');
    }

    #[Test]
    public function it_enforces_max_tags_limit(): void
    {
        $tags = [];
        for ($i = 0; $i < 20; $i++) {
            $tags[] = "tag$i";
        }
        $asset = $this->createAsset(tags: $tags);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot add more than 20');

        $asset->addTag('one-too-many');
    }

    #[Test]
    public function it_can_remove_tags(): void
    {
        $asset = $this->createAsset(tags: ['photo', 'landscape']);

        $asset->removeTag('photo');

        $this->assertSame(['landscape'], $asset->tags());
        $this->assertFalse($asset->hasTag('photo'));
    }

    #[Test]
    public function removing_non_existent_tag_is_idempotent(): void
    {
        $asset = $this->createAsset();

        $asset->removeTag('nonexistent');

        $this->assertNull($asset->updatedAt());
    }

    // ─── Metadata ────────────────────────────────────────────────────

    #[Test]
    public function it_can_update_description(): void
    {
        $asset = $this->createAsset();

        $asset->updateDescription('New description');

        $this->assertSame('New description', $asset->description());
    }

    #[Test]
    public function it_rejects_empty_description(): void
    {
        $asset = $this->createAsset();

        $this->expectException(\InvalidArgumentException::class);

        $asset->updateDescription('');
    }

    #[Test]
    public function it_can_be_renamed(): void
    {
        $asset = $this->createAsset();

        $asset->rename(new FileName('new-name.png'));

        $this->assertSame('new-name.png', $asset->fileName()->value());
    }

    #[Test]
    public function renaming_to_same_name_is_idempotent(): void
    {
        $asset = $this->createAsset();

        $asset->rename(new FileName('photo.jpg'));

        $this->assertNull($asset->updatedAt());
    }

    // ─── Serialization ───────────────────────────────────────────────

    #[Test]
    public function it_serializes_to_array(): void
    {
        $asset = $this->createAsset(
            folderId: 3,
            description: 'Test asset',
            tags: ['test'],
        );

        $data = $asset->toArray();

        $this->assertSame(1, $data['id']);
        $this->assertSame('photo.jpg', $data['file_name']);
        $this->assertSame(1024, $data['file_size']);
        $this->assertSame('1 KB', $data['file_size_human']);
        $this->assertSame('image/jpeg', $data['mime_type']);
        $this->assertSame('image', $data['category']);
        $this->assertSame('draft', $data['status']);
        $this->assertSame(3, $data['folder_id']);
        $this->assertSame(1, $data['uploaded_by']);
        $this->assertSame('Test asset', $data['description']);
        $this->assertSame(['test'], $data['tags']);
    }
}

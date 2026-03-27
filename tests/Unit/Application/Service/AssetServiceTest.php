<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Service;

use App\Application\DTO\UploadAssetDTO;
use App\Application\Service\AssetService;
use App\Domain\Entity\Asset;
use App\Domain\Entity\Folder;
use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Exception\AssetNotFoundException;
use App\Domain\Exception\FolderNotFoundException;
use App\Domain\Exception\InvalidAssetTransitionException;
use App\Domain\Repository\AssetRepositoryInterface;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\AssetStatus;
use App\Domain\ValueObject\FileName;
use App\Domain\ValueObject\FileSize;
use App\Domain\ValueObject\FolderId;
use App\Domain\ValueObject\MimeType;
use App\Domain\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetService::class)]
final class AssetServiceTest extends TestCase
{
    private AssetRepositoryInterface&MockObject $assetRepo;
    private FolderRepositoryInterface&MockObject $folderRepo;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private AssetService $service;

    protected function setUp(): void
    {
        $this->assetRepo = $this->createMock(AssetRepositoryInterface::class);
        $this->folderRepo = $this->createMock(FolderRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->service = new AssetService($this->assetRepo, $this->folderRepo, $this->eventDispatcher);
    }

    private function createAsset(
        int $id = 1,
        AssetStatus $status = AssetStatus::Draft,
        ?string $description = null,
    ): Asset {
        return new Asset(
            id: new AssetId($id),
            fileName: new FileName('test.jpg'),
            fileSize: new FileSize(1024),
            mimeType: new MimeType('image/jpeg'),
            uploadedBy: new UserId(1),
            status: $status,
            description: $description,
        );
    }

    private function createFolder(int $id = 1): Folder
    {
        return new Folder(
            id: new FolderId($id),
            name: 'Test Folder',
            createdBy: new UserId(1),
        );
    }

    // ─── uploadAsset ─────────────────────────────────────────────────

    #[Test]
    public function it_uploads_a_new_asset(): void
    {
        $dto = new UploadAssetDTO(
            fileName: 'photo.jpg',
            fileSize: 2048,
            mimeType: 'image/jpeg',
            uploadedBy: 1,
        );

        $this->assetRepo->method('nextId')->willReturn(new AssetId(1));
        $this->assetRepo->expects($this->once())->method('save');

        $asset = $this->service->uploadAsset($dto);

        $this->assertSame(1, $asset->id()->value());
        $this->assertSame('photo.jpg', $asset->fileName()->value());
        $this->assertSame(AssetStatus::Draft, $asset->status());
    }

    #[Test]
    public function it_uploads_asset_to_existing_folder(): void
    {
        $dto = new UploadAssetDTO(
            fileName: 'photo.jpg',
            fileSize: 2048,
            mimeType: 'image/jpeg',
            uploadedBy: 1,
            folderId: 5,
        );

        $this->folderRepo->method('findById')->willReturn($this->createFolder(5));
        $this->assetRepo->method('nextId')->willReturn(new AssetId(1));
        $this->assetRepo->expects($this->once())->method('save');

        $asset = $this->service->uploadAsset($dto);

        $this->assertSame(5, $asset->folderId()->value());
    }

    #[Test]
    public function it_rejects_upload_to_nonexistent_folder(): void
    {
        $dto = new UploadAssetDTO(
            fileName: 'photo.jpg',
            fileSize: 2048,
            mimeType: 'image/jpeg',
            uploadedBy: 1,
            folderId: 999,
        );

        $this->folderRepo->method('findById')->willReturn(null);

        $this->expectException(FolderNotFoundException::class);

        $this->service->uploadAsset($dto);
    }

    // ─── getAssetById ────────────────────────────────────────────────

    #[Test]
    public function it_returns_asset_when_found(): void
    {
        $asset = $this->createAsset();
        $this->assetRepo->method('findById')->willReturn($asset);

        $result = $this->service->getAssetById(1);

        $this->assertSame(1, $result->id()->value());
    }

    #[Test]
    public function it_throws_when_asset_not_found(): void
    {
        $this->assetRepo->method('findById')->willReturn(null);

        $this->expectException(AssetNotFoundException::class);

        $this->service->getAssetById(999);
    }

    // ─── publishAsset ────────────────────────────────────────────────

    #[Test]
    public function it_publishes_a_draft_asset_with_description(): void
    {
        $asset = $this->createAsset(description: 'Ready to publish');
        $this->assetRepo->method('findById')->willReturn($asset);
        $this->assetRepo->expects($this->once())->method('save');

        $result = $this->service->publishAsset(1);

        $this->assertSame(AssetStatus::Published, $result->status());
    }

    #[Test]
    public function it_throws_when_publishing_without_description(): void
    {
        $asset = $this->createAsset(); // No description
        $this->assetRepo->method('findById')->willReturn($asset);

        $this->expectException(\DomainException::class);

        $this->service->publishAsset(1);
    }

    // ─── archiveAsset ────────────────────────────────────────────────

    #[Test]
    public function it_archives_a_published_asset(): void
    {
        $asset = $this->createAsset(description: 'Desc');
        $asset->publish();

        $this->assetRepo->method('findById')->willReturn($asset);
        $this->assetRepo->expects($this->once())->method('save');

        $result = $this->service->archiveAsset(1);

        $this->assertSame(AssetStatus::Archived, $result->status());
    }

    #[Test]
    public function it_throws_when_archiving_draft_asset(): void
    {
        $asset = $this->createAsset();
        $this->assetRepo->method('findById')->willReturn($asset);

        $this->expectException(InvalidAssetTransitionException::class);

        $this->service->archiveAsset(1);
    }

    // ─── restoreAsset ────────────────────────────────────────────────

    #[Test]
    public function it_restores_an_archived_asset(): void
    {
        $asset = $this->createAsset(description: 'Desc');
        $asset->publish();
        $asset->archive();

        $this->assetRepo->method('findById')->willReturn($asset);
        $this->assetRepo->expects($this->once())->method('save');

        $result = $this->service->restoreAsset(1);

        $this->assertSame(AssetStatus::Draft, $result->status());
    }

    // ─── moveAsset ───────────────────────────────────────────────────

    #[Test]
    public function it_moves_asset_to_folder(): void
    {
        $asset = $this->createAsset();
        $this->assetRepo->method('findById')->willReturn($asset);
        $this->folderRepo->method('findById')->willReturn($this->createFolder(5));
        $this->assetRepo->expects($this->once())->method('save');

        $result = $this->service->moveAsset(1, 5);

        $this->assertSame(5, $result->folderId()->value());
    }

    #[Test]
    public function it_throws_when_moving_to_nonexistent_folder(): void
    {
        $asset = $this->createAsset();
        $this->assetRepo->method('findById')->willReturn($asset);
        $this->folderRepo->method('findById')->willReturn(null);

        $this->expectException(FolderNotFoundException::class);

        $this->service->moveAsset(1, 999);
    }

    // ─── tagAsset ────────────────────────────────────────────────────

    #[Test]
    public function it_adds_a_tag_to_asset(): void
    {
        $asset = $this->createAsset();
        $this->assetRepo->method('findById')->willReturn($asset);
        $this->assetRepo->expects($this->once())->method('save');

        $result = $this->service->tagAsset(1, 'landscape');

        $this->assertTrue($result->hasTag('landscape'));
    }

    // ─── deleteAsset ─────────────────────────────────────────────────

    #[Test]
    public function it_deletes_an_existing_asset(): void
    {
        $asset = $this->createAsset();
        $this->assetRepo->method('findById')->willReturn($asset);
        $this->assetRepo->expects($this->once())->method('delete');

        $this->service->deleteAsset(1);
    }

    #[Test]
    public function it_throws_when_deleting_nonexistent_asset(): void
    {
        $this->assetRepo->method('findById')->willReturn(null);

        $this->expectException(AssetNotFoundException::class);

        $this->service->deleteAsset(999);
    }
}

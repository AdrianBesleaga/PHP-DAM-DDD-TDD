<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\DTO\CreateFolderDTO;
use App\Application\DTO\UploadAssetDTO;
use App\Application\EventHandler\LogAssetPublishedHandler;
use App\Application\Service\AssetService;
use App\Application\Service\FolderService;
use App\Domain\Exception\AssetNotFoundException;
use App\Domain\Exception\FolderNotFoundException;
use App\Domain\ValueObject\AssetStatus;
use App\Infrastructure\Event\SimpleEventDispatcher;
use App\Infrastructure\Persistence\InMemoryAssetRepository;
use App\Infrastructure\Persistence\InMemoryFolderRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration test — wires real repositories to services,
 * testing the full DAM workflow without mocks.
 */
#[CoversClass(AssetService::class)]
#[CoversClass(FolderService::class)]
#[CoversClass(InMemoryAssetRepository::class)]
#[CoversClass(InMemoryFolderRepository::class)]
final class AssetWorkflowTest extends TestCase
{
    private AssetService $assetService;
    private FolderService $folderService;

    protected function setUp(): void
    {
        $assetRepo = new InMemoryAssetRepository();
        $folderRepo = new InMemoryFolderRepository();
        $logger = new NullLogger();
        $eventDispatcher = new SimpleEventDispatcher(
            new LogAssetPublishedHandler($logger),
            $logger,
        );
        $this->assetService = new AssetService($assetRepo, $folderRepo, $eventDispatcher);
        $this->folderService = new FolderService($folderRepo);
    }

    #[Test]
    public function full_dam_workflow(): void
    {
        // 1. Create folder hierarchy
        $marketing = $this->folderService->createFolder(
            new CreateFolderDTO(name: 'Marketing', createdBy: 1)
        );
        $this->assertSame(1, $marketing->id()->value());
        $this->assertTrue($marketing->isRoot());

        $photos = $this->folderService->createFolder(
            new CreateFolderDTO(name: 'Photos', createdBy: 1, parentId: $marketing->id()->value())
        );
        $this->assertSame($marketing->id()->value(), $photos->parentId()->value());

        // 2. Upload asset to folder
        $asset = $this->assetService->uploadAsset(new UploadAssetDTO(
            fileName: 'hero.jpg',
            fileSize: 2_000_000,
            mimeType: 'image/jpeg',
            uploadedBy: 1,
            folderId: $photos->id()->value(),
            tags: ['hero', 'banner'],
        ));

        $this->assertSame(AssetStatus::Draft, $asset->status());
        $this->assertSame($photos->id()->value(), $asset->folderId()->value());
        $this->assertTrue($asset->hasTag('hero'));

        // 3. Cannot publish without description
        $this->expectExceptionTemporary(
            \DomainException::class,
            fn() =>
            $this->assetService->publishAsset($asset->id()->value())
        );

        // 4. Add description and publish
        $asset->updateDescription('Main hero banner');
        $published = $this->assetService->publishAsset($asset->id()->value());
        $this->assertSame(AssetStatus::Published, $published->status());

        // 5. Archive
        $archived = $this->assetService->archiveAsset($asset->id()->value());
        $this->assertSame(AssetStatus::Archived, $archived->status());

        // 6. Restore to draft
        $restored = $this->assetService->restoreAsset($asset->id()->value());
        $this->assertSame(AssetStatus::Draft, $restored->status());

        // 7. Tag management
        $tagged = $this->assetService->tagAsset($asset->id()->value(), 'homepage');
        $this->assertTrue($tagged->hasTag('homepage'));

        $untagged = $this->assetService->untagAsset($asset->id()->value(), 'banner');
        $this->assertFalse($untagged->hasTag('banner'));

        // 8. Delete the asset
        $this->assetService->deleteAsset($asset->id()->value());

        $this->expectException(AssetNotFoundException::class);
        $this->assetService->getAssetById($asset->id()->value());
    }

    #[Test]
    public function folder_hierarchy_management(): void
    {
        // Create hierarchy
        $root = $this->folderService->createFolder(
            new CreateFolderDTO(name: 'Root', createdBy: 1)
        );
        $child = $this->folderService->createFolder(
            new CreateFolderDTO(name: 'Child', createdBy: 1, parentId: $root->id()->value())
        );

        // List root folders
        $roots = $this->folderService->listRootFolders();
        $this->assertCount(1, $roots);

        // List subfolders
        $subs = $this->folderService->listSubfolders($root->id()->value());
        $this->assertCount(1, $subs);

        // Rename
        $renamed = $this->folderService->renameFolder($child->id()->value(), 'Renamed Child');
        $this->assertSame('Renamed Child', $renamed->name());

        // Move to root
        $moved = $this->folderService->moveFolder($child->id()->value(), null);
        $this->assertTrue($moved->isRoot());

        // Delete
        $this->folderService->deleteFolder($child->id()->value());
        $this->expectException(FolderNotFoundException::class);
        $this->folderService->getFolderById($child->id()->value());
    }

    #[Test]
    public function it_prevents_upload_to_nonexistent_folder(): void
    {
        $this->expectException(FolderNotFoundException::class);

        $this->assetService->uploadAsset(new UploadAssetDTO(
            fileName: 'test.pdf',
            fileSize: 1024,
            mimeType: 'application/pdf',
            uploadedBy: 1,
            folderId: 999,
        ));
    }

    #[Test]
    public function it_assigns_sequential_asset_ids(): void
    {
        $a1 = $this->assetService->uploadAsset(new UploadAssetDTO(
            fileName: 'one.jpg',
            fileSize: 100,
            mimeType: 'image/jpeg',
            uploadedBy: 1
        ));
        $a2 = $this->assetService->uploadAsset(new UploadAssetDTO(
            fileName: 'two.jpg',
            fileSize: 200,
            mimeType: 'image/jpeg',
            uploadedBy: 1
        ));

        $this->assertSame(1, $a1->id()->value());
        $this->assertSame(2, $a2->id()->value());
    }

    /**
     * Helper to assert an exception is thrown without stopping the test.
     */
    private function expectExceptionTemporary(string $exceptionClass, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected exception $exceptionClass was not thrown");
        } catch (\Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);
        }
    }
}

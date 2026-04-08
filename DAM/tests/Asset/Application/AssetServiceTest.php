<?php
declare(strict_types=1);

namespace Tests\Asset\Application;

use App\Asset\Application\AssetService;
use App\Asset\Domain\Asset;
use App\Asset\Infrastructure\InMemoryAssetRepository;
use PHPUnit\Framework\TestCase;

class AssetServiceTest extends TestCase
{
    private AssetService $assetService;

    protected function setUp(): void
    {
        $repository = new InMemoryAssetRepository();
        $this->assetService = new AssetService($repository);
    }

    public function testUploadAsset(): void
    {
        $asset = $this->assetService->uploadAsset('Logo', 'Company Logo', 'tenant-1', 1024, 'image/png');
        
        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertNotEmpty($asset->getId()->getValue());
        $this->assertEquals('Logo', $asset->getName());
        $this->assertEquals('tenant-1', $asset->getTenantId()->getValue());
        $this->assertEquals('image/png', $asset->getDetails()->getType());
    }

    public function testUpdateAsset(): void
    {
        $asset = $this->assetService->uploadAsset('Logo', 'Company Logo', 'tenant-1', 1024, 'image/png');
        $updatedAsset = $this->assetService->updateAsset($asset->getId()->getValue(), 'New Logo', 'Updated Description');
        
        $this->assertNotNull($updatedAsset);
        $this->assertEquals('New Logo', $updatedAsset->getName());
        $this->assertEquals('Updated Description', $updatedAsset->getDescription());
    }

    public function testConvertAsset(): void
    {
        $asset = $this->assetService->uploadAsset('Document', 'Text Doc', 'tenant-1', 500, 'text/plain');
        $convertedAsset = $this->assetService->convertAsset($asset->getId()->getValue(), 'application/pdf', 800, 'http://example.com/doc.pdf');
        
        $this->assertNotNull($convertedAsset);
        $this->assertEquals('application/pdf', $convertedAsset->getDetails()->getType());
        $this->assertEquals(800, $convertedAsset->getDetails()->getSize());
        $this->assertEquals('http://example.com/doc.pdf', $convertedAsset->getDetails()->getImageUrl());
    }

    public function testAddTagAndMetadata(): void
    {
        $asset = $this->assetService->uploadAsset('Photo', 'Profile Pic', 'tenant-1', 1024, 'image/jpeg');
        
        $this->assetService->addTagToAsset($asset->getId()->getValue(), 'Profile');
        $this->assetService->addMetadataToAsset($asset->getId()->getValue(), 'Resolution', '1080p');
        
        $updatedAsset = $this->assetService->addTagToAsset($asset->getId()->getValue(), 'User');
        
        $this->assertContains('Profile', $updatedAsset->getTags());
        $this->assertContains('User', $updatedAsset->getTags());
        $this->assertArrayHasKey('Resolution', $updatedAsset->getMetadata());
        $this->assertEquals('1080p', $updatedAsset->getMetadata()['Resolution']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Event;

use App\Domain\Entity\Asset;
use App\Domain\Entity\User;
use App\Domain\Event\AssetArchived;
use App\Domain\Event\AssetPublished;
use App\Domain\Event\UserSuspended;
use App\Domain\ValueObject\AssetId;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\FileName;
use App\Domain\ValueObject\FileSize;
use App\Domain\ValueObject\MimeType;
use App\Domain\ValueObject\UserId;
use App\Domain\ValueObject\UserStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetPublished::class)]
#[CoversClass(AssetArchived::class)]
#[CoversClass(UserSuspended::class)]
final class DomainEventTest extends TestCase
{
    private function createAsset(?string $description = 'Test'): Asset
    {
        return new Asset(
            id: new AssetId(1),
            fileName: new FileName('test.jpg'),
            fileSize: new FileSize(1024),
            mimeType: new MimeType('image/jpeg'),
            uploadedBy: new UserId(1),
            description: $description,
        );
    }

    #[Test]
    public function publishing_asset_records_event(): void
    {
        $asset = $this->createAsset();

        $asset->publish();

        $events = $asset->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AssetPublished::class, $events[0]);

        /** @var AssetPublished $event */
        $event = $events[0];
        $this->assertSame(1, $event->assetId->value());
        $this->assertSame('test.jpg', $event->fileName);
        $this->assertNotNull($event->occurredAt());
    }

    #[Test]
    public function archiving_asset_records_event(): void
    {
        $asset = $this->createAsset();
        $asset->publish();
        $asset->pullDomainEvents(); // Clear publish event

        $asset->archive();

        $events = $asset->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AssetArchived::class, $events[0]);

        /** @var AssetArchived $event */
        $event = $events[0];
        $this->assertSame('test.jpg', $event->fileName);
    }

    #[Test]
    public function suspending_user_records_event(): void
    {
        $user = new User(
            id: new UserId(1),
            name: 'Alice',
            email: new Email('alice@example.com'),
            status: UserStatus::Active,
        );

        $user->suspend();

        $events = $user->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserSuspended::class, $events[0]);

        /** @var UserSuspended $event */
        $event = $events[0];
        $this->assertSame(1, $event->userId->value());
        $this->assertSame('Alice', $event->userName);
    }

    #[Test]
    public function pull_clears_events(): void
    {
        $asset = $this->createAsset();
        $asset->publish();

        $first = $asset->pullDomainEvents();
        $second = $asset->pullDomainEvents();

        $this->assertCount(1, $first);
        $this->assertCount(0, $second); // Cleared after first pull
    }

    #[Test]
    public function peek_does_not_clear_events(): void
    {
        $asset = $this->createAsset();
        $asset->publish();

        $first = $asset->peekDomainEvents();
        $second = $asset->peekDomainEvents();

        $this->assertCount(1, $first);
        $this->assertCount(1, $second); // Still there
    }

    #[Test]
    public function multiple_transitions_record_multiple_events(): void
    {
        $asset = $this->createAsset();

        $asset->publish();
        $asset->archive();

        $events = $asset->pullDomainEvents();
        $this->assertCount(2, $events);
        $this->assertInstanceOf(AssetPublished::class, $events[0]);
        $this->assertInstanceOf(AssetArchived::class, $events[1]);
    }
}

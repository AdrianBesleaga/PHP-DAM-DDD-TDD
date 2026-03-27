<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\AssetStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetStatus::class)]
final class AssetStatusTest extends TestCase
{
    #[Test]
    public function draft_can_transition_to_published(): void
    {
        $this->assertTrue(AssetStatus::Draft->canTransitionTo(AssetStatus::Published));
    }

    #[Test]
    public function draft_cannot_transition_to_archived(): void
    {
        $this->assertFalse(AssetStatus::Draft->canTransitionTo(AssetStatus::Archived));
    }

    #[Test]
    public function published_can_transition_to_archived(): void
    {
        $this->assertTrue(AssetStatus::Published->canTransitionTo(AssetStatus::Archived));
    }

    #[Test]
    public function published_cannot_transition_to_draft(): void
    {
        $this->assertFalse(AssetStatus::Published->canTransitionTo(AssetStatus::Draft));
    }

    #[Test]
    public function archived_can_transition_to_draft(): void
    {
        $this->assertTrue(AssetStatus::Archived->canTransitionTo(AssetStatus::Draft));
    }

    #[Test]
    public function archived_cannot_transition_to_published(): void
    {
        $this->assertFalse(AssetStatus::Archived->canTransitionTo(AssetStatus::Published));
    }

    #[Test]
    public function no_self_transitions(): void
    {
        $this->assertFalse(AssetStatus::Draft->canTransitionTo(AssetStatus::Draft));
        $this->assertFalse(AssetStatus::Published->canTransitionTo(AssetStatus::Published));
        $this->assertFalse(AssetStatus::Archived->canTransitionTo(AssetStatus::Archived));
    }

    #[Test]
    public function it_provides_labels(): void
    {
        $this->assertSame('Draft', AssetStatus::Draft->label());
        $this->assertSame('Published', AssetStatus::Published->label());
        $this->assertSame('Archived', AssetStatus::Archived->label());
    }

    #[Test]
    public function it_detects_published_status(): void
    {
        $this->assertTrue(AssetStatus::Published->isPublished());
        $this->assertFalse(AssetStatus::Draft->isPublished());
    }

    #[Test]
    public function it_detects_archived_status(): void
    {
        $this->assertTrue(AssetStatus::Archived->isArchived());
        $this->assertFalse(AssetStatus::Draft->isArchived());
    }
}

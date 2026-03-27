<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\FileSize;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSize::class)]
final class FileSizeTest extends TestCase
{
    #[Test]
    public function it_stores_bytes(): void
    {
        $size = new FileSize(1024);

        $this->assertSame(1024, $size->bytes());
    }

    #[Test]
    public function it_converts_to_kilobytes(): void
    {
        $size = new FileSize(2048);

        $this->assertSame(2.0, $size->toKilobytes());
    }

    #[Test]
    public function it_converts_to_megabytes(): void
    {
        $size = new FileSize(5 * 1024 * 1024);

        $this->assertSame(5.0, $size->toMegabytes());
    }

    #[Test]
    public function it_formats_bytes_human_readable(): void
    {
        $this->assertSame('500 B', (new FileSize(500))->toHumanReadable());
        $this->assertSame('1 KB', (new FileSize(1024))->toHumanReadable());
        $this->assertSame('2.5 MB', (new FileSize(2_621_440))->toHumanReadable());
    }

    #[Test]
    public function it_accepts_zero_bytes(): void
    {
        $size = new FileSize(0);

        $this->assertSame(0, $size->bytes());
        $this->assertSame('0 B', $size->toHumanReadable());
    }

    #[Test]
    public function it_rejects_negative_bytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be negative');

        new FileSize(-1);
    }

    #[Test]
    public function it_rejects_files_exceeding_max_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed');

        new FileSize(101 * 1024 * 1024); // 101 MB > 100 MB limit
    }

    #[Test]
    public function it_compares_sizes(): void
    {
        $small = new FileSize(1024);
        $large = new FileSize(2048);

        $this->assertTrue($large->isLargerThan($small));
        $this->assertFalse($small->isLargerThan($large));
    }

    #[Test]
    public function it_checks_equality(): void
    {
        $a = new FileSize(1024);
        $b = new FileSize(1024);
        $c = new FileSize(2048);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $size = new FileSize(1024);

        $this->assertSame('1 KB', (string) $size);
    }
}

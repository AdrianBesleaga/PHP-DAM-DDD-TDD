<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\FileName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileName::class)]
final class FileNameTest extends TestCase
{
    #[Test]
    public function it_creates_a_valid_file_name(): void
    {
        $name = new FileName('photo.jpg');

        $this->assertSame('photo.jpg', $name->value());
        $this->assertSame('photo.jpg', (string) $name);
    }

    #[Test]
    public function it_extracts_extension(): void
    {
        $this->assertSame('jpg', (new FileName('photo.jpg'))->extension());
        $this->assertSame('pdf', (new FileName('document.pdf'))->extension());
        $this->assertSame('png', (new FileName('SCREENSHOT.PNG'))->extension());
    }

    #[Test]
    public function it_extracts_base_name(): void
    {
        $this->assertSame('photo', (new FileName('photo.jpg'))->baseName());
        $this->assertSame('my.complex.name', (new FileName('my.complex.name.pdf'))->baseName());
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        new FileName('');
    }

    #[Test]
    public function it_rejects_name_without_extension(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must have an extension');

        new FileName('noextension');
    }

    #[Test]
    public function it_rejects_names_exceeding_max_length(): void
    {
        $longName = str_repeat('a', 252) . '.jpg'; // 256 chars

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed');

        new FileName($longName);
    }

    #[Test]
    public function it_accepts_max_length_name(): void
    {
        $maxName = str_repeat('a', 251) . '.jpg'; // 255 chars exactly

        $name = new FileName($maxName);
        $this->assertSame('jpg', $name->extension());
    }

    #[Test]
    public function it_compares_by_value(): void
    {
        $a = new FileName('photo.jpg');
        $b = new FileName('photo.jpg');
        $c = new FileName('other.jpg');

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }
}

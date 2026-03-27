<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\MimeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeType::class)]
final class MimeTypeTest extends TestCase
{
    #[Test]
    public function it_creates_valid_mime_types(): void
    {
        $mime = new MimeType('image/jpeg');

        $this->assertSame('image/jpeg', $mime->value());
    }

    #[Test]
    public function it_detects_images(): void
    {
        $mime = new MimeType('image/png');

        $this->assertTrue($mime->isImage());
        $this->assertFalse($mime->isVideo());
        $this->assertFalse($mime->isDocument());
        $this->assertFalse($mime->isAudio());
    }

    #[Test]
    public function it_detects_videos(): void
    {
        $mime = new MimeType('video/mp4');

        $this->assertTrue($mime->isVideo());
        $this->assertFalse($mime->isImage());
    }

    #[Test]
    public function it_detects_documents(): void
    {
        $mime = new MimeType('application/pdf');

        $this->assertTrue($mime->isDocument());
        $this->assertFalse($mime->isImage());
    }

    #[Test]
    public function it_detects_audio(): void
    {
        $mime = new MimeType('audio/mpeg');

        $this->assertTrue($mime->isAudio());
        $this->assertFalse($mime->isImage());
    }

    #[Test]
    public function it_extracts_category(): void
    {
        $this->assertSame('image', (new MimeType('image/jpeg'))->category());
        $this->assertSame('video', (new MimeType('video/mp4'))->category());
        $this->assertSame('application', (new MimeType('application/pdf'))->category());
        $this->assertSame('audio', (new MimeType('audio/wav'))->category());
    }

    #[Test]
    public function it_rejects_unsupported_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported MIME type');

        new MimeType('application/zip');
    }

    #[Test]
    public function it_rejects_invalid_types(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MimeType('not-a-mime-type');
    }

    #[Test]
    #[DataProvider('validMimeTypeProvider')]
    public function it_accepts_all_supported_types(string $mimeType): void
    {
        $mime = new MimeType($mimeType);
        $this->assertSame($mimeType, $mime->value());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validMimeTypeProvider(): array
    {
        return [
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
            'mp4' => ['video/mp4'],
            'webm' => ['video/webm'],
            'quicktime' => ['video/quicktime'],
            'pdf' => ['application/pdf'],
            'word' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'excel' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav'],
            'ogg' => ['audio/ogg'],
        ];
    }
}

<?php
declare(strict_types=1);

namespace App\Asset\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class AssetDetails
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageUrl;

    #[ORM\Column(type: 'integer')]
    private int $size;

    #[ORM\Column(type: 'string')]
    private string $type;

    public function __construct(?string $imageUrl, int $size, string $type)
    {
        $this->imageUrl = $imageUrl;
        $this->size = $size;
        $this->type = $type;
    }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function getSize(): int { return $this->size; }
    public function getType(): string { return $this->type; }
}

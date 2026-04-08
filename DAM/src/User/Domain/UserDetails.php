<?php
declare(strict_types=1);

namespace App\User\Domain;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class UserDetails
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $imageUrl;

    public function __construct(?string $imageUrl)
    {
        $this->imageUrl = $imageUrl;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }
}

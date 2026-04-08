<?php
declare(strict_types=1);

namespace App\Asset\Domain;

use App\Shared\Domain\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'assets')]
class Asset
{
    #[ORM\Id]
    #[ORM\Column(type: 'AssetId')]
    private AssetId $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'TenantId')]
    private TenantId $tenantId;

    #[ORM\Column(type: 'string', enumType: AssetStatus::class)]
    private AssetStatus $status;

    #[ORM\Embedded(class: AssetDetails::class, columnPrefix: false)]
    private AssetDetails $details;
    
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'json')]
    private array $tags = [];

    private function __construct(
        AssetId $id,
        string $name,
        string $description,
        TenantId $tenantId,
        AssetDetails $details
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->tenantId = $tenantId;
        $this->details = $details;
        $this->status = AssetStatus::ACTIVE;
    }

    public static function upload(
        AssetId $id,
        string $name,
        string $description,
        TenantId $tenantId,
        AssetDetails $details
    ): self {
        return new self($id, $name, $description, $tenantId, $details);
    }

    public function update(string $name, string $description): void
    {
        $this->name = $name;
        $this->description = $description;
    }

    public function convert(string $newType, int $newSize, ?string $newImageUrl = null): void
    {
        $this->status = AssetStatus::CONVERTING;
        $this->details = new AssetDetails(
            $newImageUrl ?? $this->details->getImageUrl(),
            $newSize,
            $newType
        );
        $this->status = AssetStatus::ACTIVE;
    }

    public function addTag(string $tag): void
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
    }

    public function addMetadata(string $key, string $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getId(): AssetId { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): string { return $this->description; }
    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getStatus(): AssetStatus { return $this->status; }
    public function getDetails(): AssetDetails { return $this->details; }
    public function getTags(): array { return $this->tags; }
    public function getMetadata(): array { return $this->metadata; }
    
    public function toArray(): array
    {
         return [
             'id' => $this->id->getValue(),
             'name' => $this->name,
             'description' => $this->description,
             'tenantId' => $this->tenantId->getValue(),
             'status' => $this->status->value,
             'tags' => $this->tags,
             'metadata' => $this->metadata,
             'details' => [
                 'imageUrl' => $this->details->getImageUrl(),
                 'size' => $this->details->getSize(),
                 'type' => $this->details->getType(),
             ]
         ];
    }
}

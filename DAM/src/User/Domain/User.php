<?php
declare(strict_types=1);

namespace App\User\Domain;

use App\Shared\Domain\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'UserId')]
    private UserId $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'string')]
    private string $email;

    #[ORM\Column(type: 'TenantId')]
    private TenantId $tenantId;

    #[ORM\Embedded(class: UserDetails::class, columnPrefix: false)]
    private UserDetails $userDetails;

    private function __construct(
        UserId $id,
        string $name,
        string $email,
        TenantId $tenantId,
        UserDetails $userDetails
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->tenantId = $tenantId;
        $this->userDetails = $userDetails;
    }

    public static function create(
        UserId $id,
        string $name,
        string $email,
        TenantId $tenantId,
        UserDetails $userDetails
    ): self {
        return new self($id, $name, $email, $tenantId, $userDetails);
    }

    public function changeName(string $name): void
    {
        if (empty(trim($name))) {
            throw new \InvalidArgumentException("Name cannot be empty");
        }
        $this->name = $name;
    }

    public function getId(): UserId { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getTenantId(): TenantId { return $this->tenantId; }
    public function getUserDetails(): UserDetails { return $this->userDetails; }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'name' => $this->name,
            'email' => $this->email,
            'tenantId' => $this->tenantId->getValue(),
            'userDetails' => [
                'imageUrl' => $this->userDetails->getImageUrl()
            ]
        ];
    }
}

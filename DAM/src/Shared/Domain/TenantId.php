<?php
declare(strict_types=1);

namespace App\Shared\Domain;

class TenantId
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty(trim($id))) {
            throw new \InvalidArgumentException("TenantId cannot be empty");
        }
        $this->id = $id;
    }

    public function getValue(): string
    {
        return $this->id;
    }

    public function equals(TenantId $other): bool
    {
        return $this->id === $other->getValue();
    }
}

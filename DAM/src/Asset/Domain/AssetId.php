<?php
declare(strict_types=1);

namespace App\Asset\Domain;

class AssetId
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty(trim($id))) {
            throw new \InvalidArgumentException("AssetId cannot be empty");
        }
        $this->id = $id;
    }

    public function getValue(): string
    {
        return $this->id;
    }

    public function equals(AssetId $other): bool
    {
        return $this->id === $other->getValue();
    }
}

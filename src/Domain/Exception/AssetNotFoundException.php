<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class AssetNotFoundException extends \DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Asset with ID %d was not found', $id));
    }
}

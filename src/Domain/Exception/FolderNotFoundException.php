<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class FolderNotFoundException extends \DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Folder with ID %d was not found', $id));
    }
}

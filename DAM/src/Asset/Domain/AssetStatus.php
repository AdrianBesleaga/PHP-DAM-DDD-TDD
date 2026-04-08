<?php
declare(strict_types=1);

namespace App\Asset\Domain;

enum AssetStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DELETED = 'deleted';
    case ARCHIVED = 'archived';
    case CONVERTING = 'converting';
}

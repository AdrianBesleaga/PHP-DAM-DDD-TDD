<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use App\Asset\Domain\AssetId;

class AssetIdType extends StringType
{
    public const NAME = 'AssetId';

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof AssetId ? $value->getValue() : $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?AssetId
    {
        return $value === null ? null : new AssetId((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

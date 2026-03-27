<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Type;

use App\Domain\ValueObject\AssetStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Custom Doctrine type for AssetStatus enum.
 */
final class AssetStatusType extends StringType
{
    public const string NAME = 'asset_status';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AssetStatus
    {
        if ($value === null) {
            return null;
        }

        return AssetStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof AssetStatus) {
            return $value->value;
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

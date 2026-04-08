<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use App\Shared\Domain\TenantId;

class TenantIdType extends StringType
{
    public const NAME = 'TenantId';

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof TenantId ? $value->getValue() : $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantId
    {
        return $value === null ? null : new TenantId((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use App\User\Domain\UserId;

class UserIdType extends StringType
{
    public const NAME = 'UserId';

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof UserId ? $value->getValue() : $value;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?UserId
    {
        return $value === null ? null : new UserId((string) $value);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

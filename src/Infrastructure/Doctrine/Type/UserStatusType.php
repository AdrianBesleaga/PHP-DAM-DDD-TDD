<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Type;

use App\Domain\ValueObject\UserStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Custom Doctrine type for UserStatus enum.
 */
final class UserStatusType extends StringType
{
    public const string NAME = 'user_status';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserStatus
    {
        if ($value === null) {
            return null;
        }

        return UserStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UserStatus) {
            return $value->value;
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

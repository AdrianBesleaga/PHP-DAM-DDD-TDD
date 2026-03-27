<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Type;

use App\Domain\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

/**
 * Custom Doctrine type to map the Email Value Object to a DB column.
 *
 * This is how Doctrine integrates with DDD Value Objects —
 * the database stores a plain string, but PHP always works with
 * the validated Email object.
 */
final class EmailType extends StringType
{
    public const string NAME = 'email';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Email
    {
        if ($value === null) {
            return null;
        }

        return new Email((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Email) {
            return $value->value();
        }

        return (string) $value;
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

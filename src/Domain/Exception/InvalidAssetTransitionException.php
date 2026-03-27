<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\ValueObject\AssetStatus;

/**
 * Thrown when attempting an invalid asset lifecycle transition.
 *
 * Named constructor clearly communicates the from → to states.
 */
final class InvalidAssetTransitionException extends \DomainException
{
    public static function cannotTransition(AssetStatus $from, AssetStatus $to): self
    {
        return new self(
            sprintf(
                'Cannot transition asset from "%s" to "%s"',
                $from->value,
                $to->value
            )
        );
    }
}

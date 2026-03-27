<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO for updating an existing user.
 * Null fields mean "don't update this field."
 */
final readonly class UpdateUserDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? trim($data['name']) : null,
            email: isset($data['email']) ? trim($data['email']) : null,
        );
    }

    public function hasChanges(): bool
    {
        return $this->name !== null || $this->email !== null;
    }
}

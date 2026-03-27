<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * Data Transfer Object for creating a new user.
 * 
 * DTOs are simple data carriers between layers.
 * PHP 8.1's readonly properties make them naturally immutable.
 * 
 * For Java devs: this is like a Java Record.
 */
final readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    /**
     * Factory method to create from a request array (e.g., JSON body).
     * 
     * @throws \InvalidArgumentException if required fields are missing
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new \InvalidArgumentException('Field "name" is required');
        }

        if (!isset($data['email']) || trim($data['email']) === '') {
            throw new \InvalidArgumentException('Field "email" is required');
        }

        return new self(
            name: trim($data['name']),
            email: trim($data['email']),
        );
    }
}

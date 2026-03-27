<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO for creating a new folder.
 */
final readonly class CreateFolderDTO
{
    public function __construct(
        public string $name,
        public int $createdBy,
        public ?int $parentId = null,
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || trim($data['name']) === '') {
            throw new \InvalidArgumentException('Field "name" is required');
        }

        if (!isset($data['created_by']) || !is_numeric($data['created_by'])) {
            throw new \InvalidArgumentException('Field "created_by" is required and must be numeric');
        }

        return new self(
            name: trim($data['name']),
            createdBy: (int) $data['created_by'],
            parentId: isset($data['parent_id']) ? (int) $data['parent_id'] : null,
        );
    }
}

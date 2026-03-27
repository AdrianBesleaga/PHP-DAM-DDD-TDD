<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO for uploading a new asset to the DAM.
 */
final readonly class UploadAssetDTO
{
    public function __construct(
        public string $fileName,
        public int $fileSize,
        public string $mimeType,
        public int $uploadedBy,
        public ?int $folderId = null,
        public ?string $description = null,
        /** @var string[] */
        public array $tags = [],
    ) {}

    /**
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['file_name']) || trim($data['file_name']) === '') {
            throw new \InvalidArgumentException('Field "file_name" is required');
        }

        if (!isset($data['file_size']) || !is_numeric($data['file_size'])) {
            throw new \InvalidArgumentException('Field "file_size" is required and must be numeric');
        }

        if (!isset($data['mime_type']) || trim($data['mime_type']) === '') {
            throw new \InvalidArgumentException('Field "mime_type" is required');
        }

        if (!isset($data['uploaded_by']) || !is_numeric($data['uploaded_by'])) {
            throw new \InvalidArgumentException('Field "uploaded_by" is required and must be numeric');
        }

        return new self(
            fileName: trim($data['file_name']),
            fileSize: (int) $data['file_size'],
            mimeType: trim($data['mime_type']),
            uploadedBy: (int) $data['uploaded_by'],
            folderId: isset($data['folder_id']) ? (int) $data['folder_id'] : null,
            description: isset($data['description']) ? trim($data['description']) : null,
            tags: $data['tags'] ?? [],
        );
    }
}

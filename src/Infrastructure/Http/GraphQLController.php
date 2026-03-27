<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Service\AssetService;
use App\Application\Service\FolderService;
use App\Application\Service\UserService;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GraphQL controller — alternative entry point to the same Application Services.
 *
 * This demonstrates a key DDD benefit: the Domain and Application layers
 * are transport-agnostic. REST and GraphQL share the SAME services.
 *
 *   REST:    HTTP Controller → AssetService → Domain
 *   GraphQL: GraphQL Controller → AssetService → Domain
 *                                   ↑ same service
 */
final class GraphQLController
{
    private Schema $schema;

    public function __construct(
        private readonly UserService $userService,
        private readonly AssetService $assetService,
        private readonly FolderService $folderService,
    ) {
        $this->schema = $this->buildSchema();
    }

    public function handle(Request $request, Response $response): Response
    {
        /** @var array<string, mixed> $input */
        $input = (array) ($request->getParsedBody() ?? []);

        $query = (string) ($input['query'] ?? '');
        /** @var array<string, mixed>|null $variables */
        $variables = isset($input['variables']) && is_array($input['variables'])
            ? $input['variables']
            : null;

        $result = GraphQL::executeQuery(
            schema: $this->schema,
            source: $query,
            variableValues: $variables,
        );

        $response->getBody()->write((string) json_encode(
            $result->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        ));

        return $response->withHeader('Content-Type', 'application/json');
    }

    // ─── Schema Definition ──────────────────────────────────────────

    private function buildSchema(): Schema
    {
        $userType = $this->userType();
        $assetType = $this->assetType();
        $folderType = $this->folderType();

        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => [
                // ── Users ──
                'users' => [
                    'type' => Type::listOf($userType),
                    'description' => 'List all users',
                    'resolve' => fn(): array => array_map(
                        fn($u) => $u->toArray(),
                        $this->userService->listUsers(),
                    ),
                ],
                'user' => [
                    'type' => $userType,
                    'description' => 'Get user by ID',
                    'args' => [
                        'id' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => fn($root, array $args): array =>
                        $this->userService->getUserById($args['id'])->toArray(),
                ],

                // ── Assets ──
                'assets' => [
                    'type' => Type::listOf($assetType),
                    'description' => 'List all assets',
                    'resolve' => fn(): array => array_map(
                        fn($a) => $a->toArray(),
                        $this->assetService->listAssets(),
                    ),
                ],
                'asset' => [
                    'type' => $assetType,
                    'description' => 'Get asset by ID',
                    'args' => [
                        'id' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => fn($root, array $args): array =>
                        $this->assetService->getAssetById($args['id'])->toArray(),
                ],

                // ── Folders ──
                'folders' => [
                    'type' => Type::listOf($folderType),
                    'description' => 'List root folders',
                    'resolve' => fn(): array => array_map(
                        fn($f) => $f->toArray(),
                        $this->folderService->listRootFolders(),
                    ),
                ],
                'folder' => [
                    'type' => $folderType,
                    'description' => 'Get folder by ID',
                    'args' => [
                        'id' => Type::nonNull(Type::int()),
                    ],
                    'resolve' => fn($root, array $args): array =>
                        $this->folderService->getFolderById($args['id'])->toArray(),
                ],
            ],
        ]);

        return new Schema([
            'query' => $queryType,
        ]);
    }

    // ─── GraphQL Types ──────────────────────────────────────────────

    private function userType(): ObjectType
    {
        return new ObjectType([
            'name' => 'User',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'name' => Type::nonNull(Type::string()),
                'email' => Type::nonNull(Type::string()),
                'status' => Type::nonNull(Type::string()),
                'created_at' => Type::nonNull(Type::string()),
                'updated_at' => Type::string(),
            ],
        ]);
    }

    private function assetType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Asset',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'file_name' => Type::nonNull(Type::string()),
                'file_size' => Type::nonNull(Type::int()),
                'mime_type' => Type::nonNull(Type::string()),
                'status' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'tags' => Type::listOf(Type::string()),
                'folder_id' => Type::int(),
                'uploaded_by' => Type::nonNull(Type::int()),
                'created_at' => Type::nonNull(Type::string()),
                'updated_at' => Type::string(),
            ],
        ]);
    }

    private function folderType(): ObjectType
    {
        return new ObjectType([
            'name' => 'Folder',
            'fields' => [
                'id' => Type::nonNull(Type::int()),
                'name' => Type::nonNull(Type::string()),
                'parent_id' => Type::int(),
                'is_root' => Type::nonNull(Type::boolean()),
                'created_by' => Type::nonNull(Type::int()),
                'created_at' => Type::nonNull(Type::string()),
                'updated_at' => Type::string(),
            ],
        ]);
    }
}

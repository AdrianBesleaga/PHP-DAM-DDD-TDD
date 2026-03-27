<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\DTO\CreateUserDTO;
use App\Application\DTO\UploadAssetDTO;
use App\Application\EventHandler\LogAssetPublishedHandler;
use App\Application\Service\AssetService;
use App\Application\Service\FolderService;
use App\Application\Service\UserService;
use App\Infrastructure\Event\SimpleEventDispatcher;
use App\Infrastructure\Http\GraphQLController;
use App\Infrastructure\Persistence\InMemoryAssetRepository;
use App\Infrastructure\Persistence\InMemoryFolderRepository;
use App\Infrastructure\Persistence\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration tests for the GraphQL endpoint.
 *
 * Proves that GraphQL reuses the same Application Services as REST —
 * zero Domain code changes needed to add a new transport layer.
 */
#[CoversClass(GraphQLController::class)]
final class GraphQLTest extends TestCase
{
    private GraphQLController $controller;
    private UserService $userService;
    private AssetService $assetService;

    protected function setUp(): void
    {
        $userRepo = new InMemoryUserRepository();
        $assetRepo = new InMemoryAssetRepository();
        $folderRepo = new InMemoryFolderRepository();
        $logger = new NullLogger();
        $dispatcher = new SimpleEventDispatcher(
            new LogAssetPublishedHandler($logger),
            $logger,
        );

        $this->userService = new UserService($userRepo);
        $this->assetService = new AssetService($assetRepo, $folderRepo, $dispatcher);
        $folderService = new FolderService($folderRepo);

        $this->controller = new GraphQLController(
            $this->userService,
            $this->assetService,
            $folderService,
        );
    }

    /**
     * Execute a GraphQL query and return the decoded result.
     *
     * @param array<string, mixed>|null $variables
     * @return array<string, mixed>
     */
    private function query(string $query, ?array $variables = null): array
    {
        $body = ['query' => $query];
        if ($variables !== null) {
            $body['variables'] = $variables;
        }

        $request = new \Slim\Psr7\Request(
            method: 'POST',
            uri: new \Slim\Psr7\Uri('', '', 80, '/graphql'),
            headers: new \Slim\Psr7\Headers(['Content-Type' => 'application/json']),
            cookies: [],
            serverParams: [],
            body: new \Slim\Psr7\Stream(fopen('php://temp', 'r+')),
        );

        // Simulate parsed body (Slim's BodyParsingMiddleware does this)
        $request = $request->withParsedBody($body);

        $response = new \Slim\Psr7\Response();
        $result = $this->controller->handle($request, $response);

        $json = (string) $result->getBody();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true);

        return $decoded;
    }

    // ─── User Queries ───────────────────────────────────────────────

    #[Test]
    public function it_queries_empty_users_list(): void
    {
        $result = $this->query('{ users { id name email } }');

        $this->assertArrayHasKey('data', $result);
        $this->assertSame([], $result['data']['users']);
    }

    #[Test]
    public function it_queries_users_after_creation(): void
    {
        $this->userService->createUser(
            CreateUserDTO::fromArray(['name' => 'Alice', 'email' => 'alice@example.com']),
        );
        $this->userService->createUser(
            CreateUserDTO::fromArray(['name' => 'Bob', 'email' => 'bob@example.com']),
        );

        $result = $this->query('{ users { id name email status } }');

        $users = $result['data']['users'];
        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]['name']);
        $this->assertSame('alice@example.com', $users[0]['email']);
        $this->assertSame('active', $users[0]['status']);
        $this->assertSame('Bob', $users[1]['name']);
    }

    #[Test]
    public function it_queries_single_user_by_id(): void
    {
        $this->userService->createUser(
            CreateUserDTO::fromArray(['name' => 'Alice', 'email' => 'alice@example.com']),
        );

        $result = $this->query('{ user(id: 1) { id name email } }');

        $this->assertSame(1, $result['data']['user']['id']);
        $this->assertSame('Alice', $result['data']['user']['name']);
    }

    #[Test]
    public function it_returns_error_for_nonexistent_user(): void
    {
        $result = $this->query('{ user(id: 999) { id name } }');

        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    // ─── Asset Queries ──────────────────────────────────────────────

    #[Test]
    public function it_queries_assets(): void
    {
        $this->assetService->uploadAsset(new UploadAssetDTO(
            fileName: 'photo.jpg',
            fileSize: 2048,
            mimeType: 'image/jpeg',
            uploadedBy: 1,
            tags: ['hero', 'banner'],
        ));

        $result = $this->query('{ assets { id file_name mime_type status tags } }');

        $assets = $result['data']['assets'];
        $this->assertCount(1, $assets);
        $this->assertSame('photo.jpg', $assets[0]['file_name']);
        $this->assertSame('draft', $assets[0]['status']);
        $this->assertContains('hero', $assets[0]['tags']);
    }

    #[Test]
    public function it_queries_single_asset(): void
    {
        $this->assetService->uploadAsset(new UploadAssetDTO(
            fileName: 'doc.pdf',
            fileSize: 1024,
            mimeType: 'application/pdf',
            uploadedBy: 1,
        ));

        $result = $this->query('{ asset(id: 1) { id file_name file_size } }');

        $this->assertSame(1, $result['data']['asset']['id']);
        $this->assertSame('doc.pdf', $result['data']['asset']['file_name']);
        $this->assertSame(1024, $result['data']['asset']['file_size']);
    }

    // ─── Selective Fields (GraphQL advantage) ───────────────────────

    #[Test]
    public function it_returns_only_requested_fields(): void
    {
        $this->userService->createUser(
            CreateUserDTO::fromArray(['name' => 'Alice', 'email' => 'alice@example.com']),
        );

        // Only request name — no email, no status, no timestamps
        $result = $this->query('{ users { name } }');

        $user = $result['data']['users'][0];
        $this->assertSame('Alice', $user['name']);
        $this->assertArrayNotHasKey('email', $user);
        $this->assertArrayNotHasKey('status', $user);
        $this->assertArrayNotHasKey('id', $user);
    }

    // ─── Invalid Queries ────────────────────────────────────────────

    #[Test]
    public function it_handles_invalid_query_syntax(): void
    {
        $result = $this->query('{ invalid syntax !!!');

        $this->assertArrayHasKey('errors', $result);
    }

    #[Test]
    public function it_handles_unknown_fields(): void
    {
        $result = $this->query('{ users { nonExistentField } }');

        $this->assertArrayHasKey('errors', $result);
    }
}

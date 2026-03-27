<?php

declare(strict_types=1);

use App\Infrastructure\Http\AssetController;
use App\Infrastructure\Http\FolderController;
use App\Infrastructure\Http\GraphQLController;
use App\Infrastructure\Http\Middleware\CorsMiddleware;
use App\Infrastructure\Http\Middleware\JsonErrorMiddleware;
use App\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use App\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Http\UserController;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

require __DIR__ . '/../vendor/autoload.php';

// ============================================================
// ENVIRONMENT — Load .env secrets
// ============================================================

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad(); // Won't throw if .env is missing (e.g. in production with real env vars)

// ============================================================
// COMPOSITION ROOT — DI Container + Slim App
// ============================================================

// Build the DI container from config
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Create Slim app with DI container
$app = \DI\Bridge\Slim\Bridge::create($container);

// ============================================================
// MIDDLEWARE STACK (Slim uses LIFO — last added runs first)
//
//   Execution order:
//   1. JsonErrorMiddleware      ← catches ALL exceptions (including auth)
//   2. SecurityHeadersMiddleware ← adds defensive headers
//   3. CorsMiddleware            ← handles CORS + OPTIONS preflight
//   4. JwtAuthMiddleware         ← validates Bearer token
//   5. RoutingMiddleware         ← resolves the route
//   6. BodyParsingMiddleware     ← parses JSON body
// ============================================================

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// JWT Authentication — protects /api/* routes, skips public paths
$jwtSecret = $_ENV['JWT_SECRET'] ?? 'fallback-dev-secret';
$app->addMiddleware(new JwtAuthMiddleware(
    responseFactory: $app->getResponseFactory(),
    jwtSecret: $jwtSecret,
    publicPaths: ['/', '/graphql', '/api/health'],
));

// CORS — allows cross-origin requests from frontend apps
$app->addMiddleware(new CorsMiddleware(
    responseFactory: $app->getResponseFactory(),
));

// Security Headers — defense-in-depth on every response
$app->addMiddleware(new SecurityHeadersMiddleware());

// Error Handling — catches everything, maps exceptions to JSON responses
$app->addMiddleware(new JsonErrorMiddleware(
    responseFactory: $app->getResponseFactory(),
    logger: $container->get(LoggerInterface::class),
    debug: ($_ENV['APP_DEBUG'] ?? 'true') === 'true',
));

// ============================================================
// ROUTES
// ============================================================

// --- API Index ---
$app->get('/', function (Request $request, Response $response) {
    $info = [
        'name' => 'PHP DDD-TDD DAM System',
        'version' => '3.0.0',
        'architecture' => 'DDD / Hexagonal / SOLID / ACID-Ready',
         'features' => [
            'Domain Events',
            'Doctrine ORM (SQLite)',
            'DI Container (PHP-DI)',
            'PSR-3 Logging (Monolog)',
            'Centralized Error Middleware',
            'GraphQL API',
            'OpenAPI 3.1 Documentation',
            'PHPStan Level 8',
        ],
        'domains' => [
            'users' => [
                'GET /api/users' => 'List all users',
                'GET /api/users/{id}' => 'Get user by ID',
                'POST /api/users' => 'Create a new user',
                'PUT /api/users/{id}' => 'Update a user',
                'POST /api/users/{id}/suspend' => 'Suspend a user',
                'POST /api/users/{id}/reactivate' => 'Reactivate a user',
                'DELETE /api/users/{id}' => 'Delete a user',
            ],
            'assets' => [
                'GET /api/assets' => 'List all assets',
                'GET /api/assets/{id}' => 'Get asset by ID',
                'POST /api/assets' => 'Upload a new asset',
                'POST /api/assets/{id}/publish' => 'Publish an asset',
                'POST /api/assets/{id}/archive' => 'Archive an asset',
                'POST /api/assets/{id}/restore' => 'Restore to draft',
                'POST /api/assets/{id}/move' => 'Move to folder',
                'DELETE /api/assets/{id}' => 'Delete an asset',
            ],
            'folders' => [
                'GET /api/folders' => 'List root folders',
                'GET /api/folders/{id}' => 'Get folder by ID',
                'GET /api/folders/{id}/subfolders' => 'List subfolders',
                'POST /api/folders' => 'Create a new folder',
                'PUT /api/folders/{id}' => 'Rename a folder',
                'DELETE /api/folders/{id}' => 'Delete a folder',
            ],
            'graphql' => [
                'POST /graphql' => 'GraphQL endpoint (queries for users, assets, folders)',
            ],
        ],
        'documentation' => 'docs/openapi.yaml (OpenAPI 3.1)',
    ];

    $response->getBody()->write((string) json_encode($info, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// --- Health Check (for Docker / K8s / Load Balancers) ---
$app->get('/api/health', function (Request $request, Response $response) {
    $health = [
        'status' => 'healthy',
        'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        'checks' => [
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB',
        ],
    ];

    $response->getBody()->write((string) json_encode($health, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// --- User Routes ---
$app->get('/api/users', [UserController::class, 'list']);
$app->get('/api/users/{id}', [UserController::class, 'show']);
$app->post('/api/users', [UserController::class, 'create']);
$app->put('/api/users/{id}', [UserController::class, 'update']);
$app->post('/api/users/{id}/suspend', [UserController::class, 'suspend']);
$app->post('/api/users/{id}/reactivate', [UserController::class, 'reactivate']);
$app->delete('/api/users/{id}', [UserController::class, 'delete']);

// --- Asset Routes ---
$app->get('/api/assets', [AssetController::class, 'list']);
$app->get('/api/assets/{id}', [AssetController::class, 'show']);
$app->post('/api/assets', [AssetController::class, 'upload']);
$app->post('/api/assets/{id}/publish', [AssetController::class, 'publish']);
$app->post('/api/assets/{id}/archive', [AssetController::class, 'archive']);
$app->post('/api/assets/{id}/restore', [AssetController::class, 'restore']);
$app->post('/api/assets/{id}/move', [AssetController::class, 'move']);
$app->delete('/api/assets/{id}', [AssetController::class, 'delete']);

// --- Folder Routes ---
$app->get('/api/folders', [FolderController::class, 'listRoots']);
$app->get('/api/folders/{id}', [FolderController::class, 'show']);
$app->get('/api/folders/{id}/subfolders', [FolderController::class, 'subfolders']);
$app->post('/api/folders', [FolderController::class, 'create']);
$app->put('/api/folders/{id}', [FolderController::class, 'rename']);
$app->delete('/api/folders/{id}', [FolderController::class, 'delete']);

// --- GraphQL ---
$app->post('/graphql', [GraphQLController::class, 'handle']);

$app->run();

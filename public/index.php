<?php

declare(strict_types=1);

use App\Infrastructure\Http\AssetController;
use App\Infrastructure\Http\FolderController;
use App\Infrastructure\Http\Middleware\JsonErrorMiddleware;
use App\Infrastructure\Http\UserController;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

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
// MIDDLEWARE STACK
// ============================================================

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Custom JSON error middleware — replaces Slim's default HTML error pages
$app->addMiddleware(new JsonErrorMiddleware(
    responseFactory: $app->getResponseFactory(),
    logger: $container->get(LoggerInterface::class),
    debug: true, // Set to false in production
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
        ],
    ];

    $response->getBody()->write(json_encode($info, JSON_PRETTY_PRINT));
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

$app->run();

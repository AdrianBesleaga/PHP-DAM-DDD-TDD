<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

use App\Shared\Infrastructure\DoctrineEntityManagerFactory;
use Doctrine\ORM\EntityManager;

use App\User\Domain\UserRepository;
use App\User\Infrastructure\DoctrineUserRepository;
use App\User\Infrastructure\Http\Controllers\CreateUserAction;
use App\User\Infrastructure\Http\Controllers\GetUserAction;

use App\Asset\Domain\AssetRepository;
use App\Asset\Infrastructure\DoctrineAssetRepository;
use App\Asset\Infrastructure\Http\Controllers\UploadAssetAction;
use App\Asset\Infrastructure\Http\Controllers\GetAssetAction;

require __DIR__ . '/../vendor/autoload.php';

// Set up DI Container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    EntityManager::class => \DI\factory([DoctrineEntityManagerFactory::class, 'create']),
    UserRepository::class => \DI\autowire(DoctrineUserRepository::class),
    AssetRepository::class => \DI\autowire(DoctrineAssetRepository::class),
]);

try {
    $container = $containerBuilder->build();
} catch (\Exception $e) {
    die("Failed to build DI container: " . $e->getMessage());
}

// Setup App inside container
AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// Configure Routes pointing to Hexagonal Action Controllers
$app->post('/api/users', CreateUserAction::class);
$app->get('/api/users/{id}', GetUserAction::class);

$app->post('/api/assets', UploadAssetAction::class);
$app->get('/api/assets/{id}', GetAssetAction::class);

$app->run();

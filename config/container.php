<?php

declare(strict_types=1);

use App\Application\EventHandler\LogAssetPublishedHandler;
use App\Application\Service\AssetService;
use App\Application\Service\FolderService;
use App\Application\Service\UserService;
use App\Domain\Event\EventDispatcherInterface;
use App\Domain\Repository\AssetRepositoryInterface;
use App\Domain\Repository\FolderRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Event\SimpleEventDispatcher;
use App\Infrastructure\Http\AssetController;
use App\Infrastructure\Http\FolderController;
use App\Infrastructure\Http\GraphQLController;
use App\Infrastructure\Http\UserController;
use App\Infrastructure\Persistence\InMemoryAssetRepository;
use App\Infrastructure\Persistence\InMemoryFolderRepository;
use App\Infrastructure\Persistence\InMemoryUserRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * DI Container configuration.
 *
 * Maps interfaces to concrete implementations.
 * This is the ONLY place that knows about concrete classes.
 *
 * To switch to Doctrine: change InMemory* to Doctrine* implementations.
 * ZERO changes to Domain or Application layers.
 */
return [
    // ─── Logging (PSR-3) ─────────────────────────────────────────────
    LoggerInterface::class => function () {
        $logDir = __DIR__ . '/../var/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logger = new Logger('dam');
        $logger->pushHandler(new StreamHandler(
            $logDir . '/app.log',
            Logger::DEBUG
        ));
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

        return $logger;
    },

    // ─── Repositories (Interface → Implementation) ───────────────────
    // Switch these to Doctrine* implementations for real database:
    //   UserRepositoryInterface::class => DI\autowire(DoctrineUserRepository::class),
    UserRepositoryInterface::class => DI\autowire(InMemoryUserRepository::class),
    AssetRepositoryInterface::class => DI\autowire(InMemoryAssetRepository::class),
    FolderRepositoryInterface::class => DI\autowire(InMemoryFolderRepository::class),

    // ─── Application Services ────────────────────────────────────────
    UserService::class => DI\autowire(),
    AssetService::class => DI\autowire(),
    FolderService::class => DI\autowire(),

    // ─── Event System ────────────────────────────────────────────────
    EventDispatcherInterface::class => DI\autowire(SimpleEventDispatcher::class),
    LogAssetPublishedHandler::class => DI\autowire(),

    // ─── HTTP Controllers ────────────────────────────────────────────
    UserController::class => DI\autowire(),
    AssetController::class => DI\autowire(),
    FolderController::class => DI\autowire(),
    GraphQLController::class => DI\autowire(),
];

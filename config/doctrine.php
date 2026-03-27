<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * Doctrine EntityManager factory.
 *
 * Uses SQLite for portability — no MySQL/PostgreSQL setup needed.
 * In production, swap the connection params for your real database.
 */
return function (string $dbPath = null): EntityManager {
    $dbPath ??= __DIR__ . '/../var/database.sqlite';

    // Ensure the directory exists
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Entity paths — where Doctrine scans for mapped classes
    $entityPaths = [
        __DIR__ . '/../src/Domain/Entity',
    ];

    // Dev mode = no caching, proxy generation on-the-fly
    $isDevMode = true;

    $config = ORMSetup::createAttributeMetadataConfiguration(
        paths: $entityPaths,
        isDevMode: $isDevMode,
    );

    // SQLite connection
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'path' => $dbPath,
    ], $config);

    // Register custom Doctrine types for Value Objects
    if (!Doctrine\DBAL\Types\Type::hasType('email')) {
        Doctrine\DBAL\Types\Type::addType('email', App\Infrastructure\Doctrine\Type\EmailType::class);
    }
    if (!Doctrine\DBAL\Types\Type::hasType('asset_status')) {
        Doctrine\DBAL\Types\Type::addType('asset_status', App\Infrastructure\Doctrine\Type\AssetStatusType::class);
    }
    if (!Doctrine\DBAL\Types\Type::hasType('user_status')) {
        Doctrine\DBAL\Types\Type::addType('user_status', App\Infrastructure\Doctrine\Type\UserStatusType::class);
    }

    return new EntityManager($connection, $config);
};

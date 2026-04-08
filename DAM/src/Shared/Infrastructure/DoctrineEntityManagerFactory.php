<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use App\Shared\Infrastructure\Doctrine\UserIdType;
use App\Shared\Infrastructure\Doctrine\AssetIdType;
use App\Shared\Infrastructure\Doctrine\TenantIdType;

class DoctrineEntityManagerFactory
{
    public static function create(): EntityManager
    {
        // Register custom Identity types if not registered yet
        if (!Type::hasType(UserIdType::NAME)) { Type::addType(UserIdType::NAME, UserIdType::class); }
        if (!Type::hasType(AssetIdType::NAME)) { Type::addType(AssetIdType::NAME, AssetIdType::class); }
        if (!Type::hasType(TenantIdType::NAME)) { Type::addType(TenantIdType::NAME, TenantIdType::class); }

        $paths = [__DIR__ . '/../../User/Domain', __DIR__ . '/../../Asset/Domain'];
        $isDevMode = true;

        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

        $dbUrl = getenv('DATABASE_URL') ?: 'postgres://postgres:postgres@localhost:5432/dam';
        $parsed = parse_url($dbUrl);
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? 5432;
        $user = $parsed['user'] ?? 'postgres';
        $pass = $parsed['pass'] ?? '';
        $db   = ltrim($parsed['path'] ?? '/dam', '/');

        $connectionParams = [
            'dbname'   => $db,
            'user'     => $user,
            'password' => $pass,
            'host'     => $host,
            'port'     => $port,
            'driver'   => 'pdo_pgsql',
        ];

        $connection = DriverManager::getConnection($connectionParams, $config);

        return new EntityManager($connection, $config);
    }
}

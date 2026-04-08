<?php
declare(strict_types=1);

namespace App\Shared\Infrastructure;

use PDO;

class DatabaseConnection
{
    public static function createPdo(): PDO
    {
        $dbUrl = getenv('DATABASE_URL');
        if (!$dbUrl) {
            // Fallback for local testing if not in Replit environment
            $dbUrl = 'postgres://postgres:postgres@localhost:5432/dam';
        }

        // Parse: postgres://username:password@host:port/database
        $parsed = parse_url($dbUrl);
        if (!$parsed || !isset($parsed['host'])) {
            throw new \RuntimeException('Invalid DATABASE_URL equivalent string.');
        }

        $host = $parsed['host'];
        $port = $parsed['port'] ?? 5432;
        $user = $parsed['user'] ?? 'postgres';
        $pass = $parsed['pass'] ?? '';
        $db   = ltrim($parsed['path'] ?? '', '/');

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
        
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }
}

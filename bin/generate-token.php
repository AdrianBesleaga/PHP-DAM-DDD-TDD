<?php

declare(strict_types=1);

/**
 * Generate a test JWT token for development.
 *
 * Usage:
 *   php bin/generate-token.php
 *   php bin/generate-token.php --user-id=42
 *   php bin/generate-token.php --user-id=1 --ttl=7200
 */

require __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Parse CLI arguments
$options = getopt('', ['user-id::', 'ttl::']);
$userId = (int) ($options['user-id'] ?? 1);
$ttl = (int) ($options['ttl'] ?? 3600);

$secret = $_ENV['JWT_SECRET'] ?? 'fallback-dev-secret';

$payload = [
    'sub' => $userId,
    'iat' => time(),
    'exp' => time() + $ttl,
    'iss' => 'dam-api',
];

$token = JWT::encode($payload, $secret, 'HS256');

echo "\n";
echo "🔐 JWT Token Generated\n";
echo "───────────────────────\n";
echo "User ID:  {$userId}\n";
echo "TTL:      {$ttl}s (" . round($ttl / 60) . " min)\n";
echo "Expires:  " . date('Y-m-d H:i:s', time() + $ttl) . "\n";
echo "\n";
echo "Token:\n";
echo $token . "\n";
echo "\n";
echo "Usage:\n";
echo "  curl -H \"Authorization: Bearer {$token}\" http://localhost:8080/api/users\n";
echo "\n";

<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * JWT Authentication Middleware.
 *
 * Validates Bearer tokens from the Authorization header.
 * On success, injects the authenticated user's claims into request attributes.
 *
 * Security is a cross-cutting concern — it belongs in middleware,
 * not in controllers or domain logic. This keeps controllers focused
 * on their single responsibility: translating HTTP to use cases.
 *
 * Public routes (e.g. /, health checks) are configurable via $publicPaths.
 */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $publicPaths Paths that don't require authentication
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $jwtSecret,
        private readonly array $publicPaths = ['/', '/graphql'],
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Skip auth for public paths
        $path = $request->getUri()->getPath();
        if ($this->isPublicPath($path)) {
            return $handler->handle($request);
        }

        // Skip auth for OPTIONS (CORS preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader === '') {
            return $this->unauthorizedResponse('Missing Authorization header');
        }

        // Extract "Bearer <token>"
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorizedResponse('Invalid Authorization format. Expected: Bearer <token>');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            // Inject authenticated user info into request attributes
            $request = $request->withAttribute('auth_user_id', $decoded->sub ?? null);
            $request = $request->withAttribute('auth_claims', (array) $decoded);

            return $handler->handle($request);
        } catch (ExpiredException) {
            return $this->errorResponse(403, 'Token expired');
        } catch (\Throwable) {
            return $this->unauthorizedResponse('Invalid token');
        }
    }

    private function isPublicPath(string $path): bool
    {
        foreach ($this->publicPaths as $publicPath) {
            if ($path === $publicPath) {
                return true;
            }
        }

        return false;
    }

    private function unauthorizedResponse(string $message): ResponseInterface
    {
        return $this->errorResponse(401, $message);
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write((string) json_encode(
            ['error' => $message],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        ));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

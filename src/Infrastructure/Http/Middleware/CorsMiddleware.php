<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS Middleware — handles Cross-Origin Resource Sharing.
 *
 * Required when a frontend (e.g. React SPA on localhost:3000)
 * calls the API on a different origin (localhost:8080).
 *
 * Handles:
 * - Preflight OPTIONS requests (automatic 204 response)
 * - CORS headers on all responses
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly string $allowedOrigins = '*',
        private readonly string $allowedMethods = 'GET, POST, PUT, DELETE, OPTIONS',
        private readonly string $allowedHeaders = 'Content-Type, Authorization, Accept',
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->responseFactory->createResponse(204);

            return $this->addCorsHeaders($response);
        }

        // Process the request, then add CORS headers to the response
        $response = $handler->handle($request);

        return $this->addCorsHeaders($response);
    }

    private function addCorsHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->allowedOrigins)
            ->withHeader('Access-Control-Allow-Methods', $this->allowedMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders)
            ->withHeader('Access-Control-Max-Age', '86400');
    }
}

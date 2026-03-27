<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Exception\AssetNotFoundException;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\FolderNotFoundException;
use App\Domain\Exception\InvalidAssetTransitionException;
use App\Domain\Exception\UserNotFoundException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Centralized error-handling middleware.
 *
 * Replaces repetitive try/catch blocks in every controller method.
 * Maps domain exceptions to appropriate HTTP status codes.
 *
 * This is the Single Responsibility Principle (SRP) in action:
 * controllers focus on happy paths, middleware handles errors.
 */
final class JsonErrorMiddleware implements MiddlewareInterface
{
    /** @var array<class-string<\Throwable>, int> */
    private const array EXCEPTION_STATUS_MAP = [
        UserNotFoundException::class => 404,
        AssetNotFoundException::class => 404,
        FolderNotFoundException::class => 404,
        DuplicateEmailException::class => 409,
        InvalidAssetTransitionException::class => 422,
        \DomainException::class => 422,
        \InvalidArgumentException::class => 422,
    ];

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
        private readonly bool $debug = false,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    private function handleException(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $statusCode = $this->resolveStatusCode($e);

        // Log server errors; domain errors are expected
        if ($statusCode >= 500) {
            $this->logger->error('Unhandled exception', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'uri' => (string) $request->getUri(),
                'trace' => $this->debug ? $e->getTraceAsString() : null,
            ]);
        } else {
            $this->logger->info('Domain exception', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'status' => $statusCode,
            ]);
        }

        $body = ['error' => $e->getMessage()];

        if ($this->debug && $statusCode >= 500) {
            $body['exception'] = $e::class;
            $body['trace'] = explode("\n", $e->getTraceAsString());
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write((string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function resolveStatusCode(\Throwable $e): int
    {
        // Check exact class match first, then parent classes
        foreach (self::EXCEPTION_STATUS_MAP as $exceptionClass => $code) {
            if ($e instanceof $exceptionClass) {
                return $code;
            }
        }

        return 500; // Unknown error
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Middleware;

use App\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Stream;
use Slim\Psr7\Uri;

#[CoversClass(JwtAuthMiddleware::class)]
final class JwtAuthMiddlewareTest extends TestCase
{
    private const string SECRET = 'test-secret-key-for-unit-tests-must-be-at-least-32-bytes-long';

    private JwtAuthMiddleware $middleware;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->middleware = new JwtAuthMiddleware(
            responseFactory: $this->responseFactory,
            jwtSecret: self::SECRET,
            publicPaths: ['/', '/health'],
        );
    }

    private function createRequest(string $path, string $method = 'GET', string $authHeader = ''): ServerRequestInterface
    {
        $headers = new Headers(['Content-Type' => 'application/json']);
        if ($authHeader !== '') {
            $headers = new Headers([
                'Content-Type' => 'application/json',
                'Authorization' => $authHeader,
            ]);
        }

        return new Request(
            method: $method,
            uri: new Uri('', '', 80, $path),
            headers: $headers,
            cookies: [],
            serverParams: [],
            body: new Stream(fopen('php://temp', 'r+')),
        );
    }

    private function createHandler(): SpyRequestHandler
    {
        return new SpyRequestHandler($this->responseFactory);
    }

    private function generateToken(int $userId = 1, ?int $exp = null): string
    {
        $payload = [
            'sub' => $userId,
            'iat' => time(),
            'exp' => $exp ?? time() + 3600,
        ];

        return JWT::encode($payload, self::SECRET, 'HS256');
    }

    // ─── Public Routes ──────────────────────────────────────────────

    #[Test]
    public function it_allows_public_paths_without_token(): void
    {
        $request = $this->createRequest('/');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function it_allows_options_preflight_without_token(): void
    {
        $request = $this->createRequest('/api/users', 'OPTIONS');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Protected Routes ───────────────────────────────────────────

    #[Test]
    public function it_rejects_request_without_authorization_header(): void
    {
        $request = $this->createRequest('/api/users');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('Missing', $body['error']);
    }

    #[Test]
    public function it_rejects_non_bearer_authorization(): void
    {
        $request = $this->createRequest('/api/users', 'GET', 'Basic abc123');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('Bearer', $body['error']);
    }

    #[Test]
    public function it_rejects_invalid_token(): void
    {
        $request = $this->createRequest('/api/users', 'GET', 'Bearer invalid.token.here');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function it_rejects_expired_token(): void
    {
        $token = $this->generateToken(userId: 1, exp: time() - 3600);
        $request = $this->createRequest('/api/users', 'GET', 'Bearer ' . $token);
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('expired', $body['error']);
    }

    // ─── Valid Token ────────────────────────────────────────────────

    #[Test]
    public function it_allows_valid_token_and_injects_user_id(): void
    {
        $token = $this->generateToken(userId: 42);
        $request = $this->createRequest('/api/users', 'GET', 'Bearer ' . $token);
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(42, $handler->receivedRequest?->getAttribute('auth_user_id'));
    }

    #[Test]
    public function it_injects_claims_into_request_attributes(): void
    {
        $token = $this->generateToken(userId: 7);
        $request = $this->createRequest('/api/assets', 'GET', 'Bearer ' . $token);
        $handler = $this->createHandler();

        $this->middleware->process($request, $handler);

        $claims = $handler->receivedRequest?->getAttribute('auth_claims');
        $this->assertIsArray($claims);
        $this->assertSame(7, $claims['sub']);
    }
}

/**
 * Test helper: a request handler that captures the request for assertions.
 */
final class SpyRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $receivedRequest = null;

    public function __construct(
        private readonly ResponseFactoryInterface $factory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->receivedRequest = $request;
        $response = $this->factory->createResponse(200);
        $response->getBody()->write('OK');

        return $response;
    }
}

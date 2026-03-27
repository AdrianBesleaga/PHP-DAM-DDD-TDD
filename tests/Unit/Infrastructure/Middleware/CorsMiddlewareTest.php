<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Middleware;

use App\Infrastructure\Http\Middleware\CorsMiddleware;
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

#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    private CorsMiddleware $middleware;
    private ResponseFactory $responseFactory;

    protected function setUp(): void
    {
        $this->responseFactory = new ResponseFactory();
        $this->middleware = new CorsMiddleware(
            responseFactory: $this->responseFactory,
        );
    }

    private function createRequest(string $method = 'GET'): ServerRequestInterface
    {
        return new Request(
            method: $method,
            uri: new Uri('', '', 80, '/api/users'),
            headers: new Headers([]),
            cookies: [],
            serverParams: [],
            body: new Stream(fopen('php://temp', 'r+')),
        );
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class ($this->responseFactory) implements RequestHandlerInterface {
            public function __construct(
                private readonly ResponseFactoryInterface $factory,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse(200);
            }
        };
    }

    #[Test]
    public function it_adds_cors_headers_to_response(): void
    {
        $request = $this->createRequest();
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    #[Test]
    public function it_handles_preflight_options_with_204(): void
    {
        $request = $this->createRequest('OPTIONS');
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function it_sets_max_age_header(): void
    {
        $request = $this->createRequest();
        $handler = $this->createHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame('86400', $response->getHeaderLine('Access-Control-Max-Age'));
    }
}

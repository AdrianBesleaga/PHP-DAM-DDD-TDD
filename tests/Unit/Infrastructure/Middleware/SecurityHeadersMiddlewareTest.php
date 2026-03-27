<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Middleware;

use App\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
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

#[CoversClass(SecurityHeadersMiddleware::class)]
final class SecurityHeadersMiddlewareTest extends TestCase
{
    private SecurityHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new SecurityHeadersMiddleware();
    }

    private function createRequest(): ServerRequestInterface
    {
        return new Request(
            method: 'GET',
            uri: new Uri('', '', 80, '/api/users'),
            headers: new Headers([]),
            cookies: [],
            serverParams: [],
            body: new Stream(fopen('php://temp', 'r+')),
        );
    }

    private function createHandler(): RequestHandlerInterface
    {
        $factory = new ResponseFactory();

        return new class ($factory) implements RequestHandlerInterface {
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
    public function it_adds_x_content_type_options(): void
    {
        $response = $this->middleware->process($this->createRequest(), $this->createHandler());
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    #[Test]
    public function it_adds_x_frame_options(): void
    {
        $response = $this->middleware->process($this->createRequest(), $this->createHandler());
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    #[Test]
    public function it_adds_strict_transport_security(): void
    {
        $response = $this->middleware->process($this->createRequest(), $this->createHandler());
        $this->assertStringContainsString('max-age=', $response->getHeaderLine('Strict-Transport-Security'));
    }

    #[Test]
    public function it_adds_content_security_policy(): void
    {
        $response = $this->middleware->process($this->createRequest(), $this->createHandler());
        $this->assertSame("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
    }

    #[Test]
    public function it_adds_referrer_policy(): void
    {
        $response = $this->middleware->process($this->createRequest(), $this->createHandler());
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
    }

    #[Test]
    public function it_adds_permissions_policy(): void
    {
        $response = $this->middleware->process($this->createRequest(), $this->createHandler());
        $this->assertStringContainsString('camera=()', $response->getHeaderLine('Permissions-Policy'));
    }
}

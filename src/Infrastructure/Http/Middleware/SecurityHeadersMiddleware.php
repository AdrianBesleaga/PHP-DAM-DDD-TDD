<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Security Headers Middleware — adds defensive HTTP headers to every response.
 *
 * These headers protect against common web attacks:
 * - Clickjacking (X-Frame-Options)
 * - MIME-type sniffing (X-Content-Type-Options)
 * - Protocol downgrade (Strict-Transport-Security)
 * - Data exfiltration (Content-Security-Policy)
 *
 * For an API-only backend with no HTML output, these headers
 * provide defense-in-depth even though the attack surface is minimal.
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        return $response
            // Prevent browser from MIME-sniffing the content-type
            ->withHeader('X-Content-Type-Options', 'nosniff')

            // Prevent this API from being iframed (clickjacking defense)
            ->withHeader('X-Frame-Options', 'DENY')

            // Force HTTPS in production (1 year, include subdomains)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')

            // API-only: no scripts, no styles, no frames — nothing
            ->withHeader('Content-Security-Policy', "default-src 'none'")

            // Prevent referrer leakage
            ->withHeader('Referrer-Policy', 'no-referrer')

            // Disable all browser features this API shouldn't need
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}

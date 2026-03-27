<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\DTO\CreateUserDTO;
use App\Application\DTO\UpdateUserDTO;
use App\Application\Service\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * HTTP Controller — translates HTTP requests into Application use cases.
 *
 * Note: No try/catch here — the JsonErrorMiddleware handles all exceptions
 * centrally, keeping controllers focused on the happy path (SRP).
 */
final class UserController
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function list(Request $request, Response $response): Response
    {
        $users = $this->userService->listUsers();
        $data = array_map(fn($user) => $user->toArray(), $users);

        return $this->jsonResponse($response, ['data' => $data]);
    }

    /** @param array<string, string> $args */
    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $this->userService->getUserById((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $user->toArray()]);
    }

    public function create(Request $request, Response $response): Response
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);
        $dto = CreateUserDTO::fromArray($body);
        $user = $this->userService->createUser($dto);

        return $this->jsonResponse($response, ['data' => $user->toArray()], 201);
    }

    /** @param array<string, string> $args */
    public function update(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);
        $dto = UpdateUserDTO::fromArray($body);
        $user = $this->userService->updateUser((int) $args['id'], $dto);

        return $this->jsonResponse($response, ['data' => $user->toArray()]);
    }

    /** @param array<string, string> $args */
    public function suspend(Request $request, Response $response, array $args): Response
    {
        $user = $this->userService->suspendUser((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $user->toArray()]);
    }

    /** @param array<string, string> $args */
    public function reactivate(Request $request, Response $response, array $args): Response
    {
        $user = $this->userService->reactivateUser((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $user->toArray()]);
    }

    /** @param array<string, string> $args */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->userService->deleteUser((int) $args['id']);

        return $this->jsonResponse($response, ['message' => 'User deleted successfully']);
    }

    /** @param array<string, mixed> $data */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

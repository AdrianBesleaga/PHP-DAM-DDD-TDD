<?php
declare(strict_types=1);

namespace App\User\Infrastructure\Http\Controllers;

use App\User\Application\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CreateUserAction
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $tenantId = $data['tenantId'] ?? '';
        $imageUrl = $data['imageUrl'] ?? null;
        
        try {
            $user = $this->userService->createUser($name, $email, $tenantId, $imageUrl);
            $response->getBody()->write(json_encode($user->toArray()));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}

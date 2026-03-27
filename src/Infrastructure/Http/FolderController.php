<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\DTO\CreateFolderDTO;
use App\Application\Service\FolderService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * HTTP Controller for Folder endpoints.
 * No try/catch — JsonErrorMiddleware handles all exceptions.
 */
final class FolderController
{
    public function __construct(
        private readonly FolderService $folderService
    ) {}

    public function listRoots(Request $request, Response $response): Response
    {
        $folders = $this->folderService->listRootFolders();
        $data = array_map(fn($f) => $f->toArray(), $folders);

        return $this->jsonResponse($response, ['data' => $data]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $folder = $this->folderService->getFolderById((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $folder->toArray()]);
    }

    public function subfolders(Request $request, Response $response, array $args): Response
    {
        $subfolders = $this->folderService->listSubfolders((int) $args['id']);
        $data = array_map(fn($f) => $f->toArray(), $subfolders);

        return $this->jsonResponse($response, ['data' => $data]);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];
        $dto = CreateFolderDTO::fromArray($body);
        $folder = $this->folderService->createFolder($dto);

        return $this->jsonResponse($response, ['data' => $folder->toArray()], 201);
    }

    public function rename(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];
        $newName = trim($body['name'] ?? '');
        $folder = $this->folderService->renameFolder((int) $args['id'], $newName);

        return $this->jsonResponse($response, ['data' => $folder->toArray()]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->folderService->deleteFolder((int) $args['id']);

        return $this->jsonResponse($response, ['message' => 'Folder deleted successfully']);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

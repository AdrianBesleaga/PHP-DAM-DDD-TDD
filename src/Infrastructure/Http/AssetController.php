<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\DTO\UploadAssetDTO;
use App\Application\Service\AssetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * HTTP Controller for Asset endpoints.
 * No try/catch — JsonErrorMiddleware handles all exceptions.
 */
final class AssetController
{
    public function __construct(
        private readonly AssetService $assetService
    ) {}

    public function list(Request $request, Response $response): Response
    {
        $assets = $this->assetService->listAssets();
        $data = array_map(fn($a) => $a->toArray(), $assets);

        return $this->jsonResponse($response, ['data' => $data]);
    }

    /** @param array<string, string> $args */
    public function show(Request $request, Response $response, array $args): Response
    {
        $asset = $this->assetService->getAssetById((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $asset->toArray()]);
    }

    public function upload(Request $request, Response $response): Response
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);
        $dto = UploadAssetDTO::fromArray($body);
        $asset = $this->assetService->uploadAsset($dto);

        return $this->jsonResponse($response, ['data' => $asset->toArray()], 201);
    }

    /** @param array<string, string> $args */
    public function publish(Request $request, Response $response, array $args): Response
    {
        $asset = $this->assetService->publishAsset((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $asset->toArray()]);
    }

    /** @param array<string, string> $args */
    public function archive(Request $request, Response $response, array $args): Response
    {
        $asset = $this->assetService->archiveAsset((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $asset->toArray()]);
    }

    /** @param array<string, string> $args */
    public function restore(Request $request, Response $response, array $args): Response
    {
        $asset = $this->assetService->restoreAsset((int) $args['id']);

        return $this->jsonResponse($response, ['data' => $asset->toArray()]);
    }

    /** @param array<string, string> $args */
    public function move(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);
        $folderId = (int) ($body['folder_id'] ?? 0);
        $asset = $this->assetService->moveAsset((int) $args['id'], $folderId);

        return $this->jsonResponse($response, ['data' => $asset->toArray()]);
    }

    /** @param array<string, string> $args */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->assetService->deleteAsset((int) $args['id']);

        return $this->jsonResponse($response, ['message' => 'Asset deleted successfully']);
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

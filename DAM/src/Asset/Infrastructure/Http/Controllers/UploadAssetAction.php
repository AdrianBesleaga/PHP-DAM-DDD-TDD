<?php
declare(strict_types=1);

namespace App\Asset\Infrastructure\Http\Controllers;

use App\Asset\Application\AssetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UploadAssetAction
{
    private AssetService $assetService;

    public function __construct(AssetService $assetService)
    {
        $this->assetService = $assetService;
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $name = $data['name'] ?? '';
        $desc = $data['description'] ?? '';
        $tenantId = $data['tenantId'] ?? '';
        $size = (int)($data['size'] ?? 0);
        $type = $data['type'] ?? '';
        $imageUrl = $data['imageUrl'] ?? null;
        
        try {
            $asset = $this->assetService->uploadAsset($name, $desc, $tenantId, $size, $type, $imageUrl);
            $response->getBody()->write(json_encode($asset->toArray()));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}

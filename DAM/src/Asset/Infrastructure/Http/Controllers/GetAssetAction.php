<?php
declare(strict_types=1);

namespace App\Asset\Infrastructure\Http\Controllers;

use App\Asset\Application\AssetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GetAssetAction
{
    private AssetService $assetService;

    public function __construct(AssetService $assetService)
    {
        $this->assetService = $assetService;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $assetId = $args['id'];
        $asset = $this->assetService->getAssetById($assetId);
        
        if (!$asset) {
            $response->getBody()->write(json_encode(['error' => 'Asset not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($asset->toArray()));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}

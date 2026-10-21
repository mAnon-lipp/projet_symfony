<?php

namespace App\Controller;

use App\Service\MusicCatalogService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CatalogController
{
    #[Route('/api/catalog/resolve-album', name: 'api_catalog_resolve_album', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resolveAlbum(Request $request, MusicCatalogService $catalogService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            throw new BadRequestHttpException('Le corps de la requête doit être un JSON valide.');
        }

        $artistName = trim((string) ($payload['artist'] ?? ''));
        $albumTitle = trim((string) ($payload['album'] ?? ''));
        if ($artistName === '' || $albumTitle === '') {
            throw new BadRequestHttpException('Les champs "artist" et "album" sont obligatoires.');
        }

        $album = $catalogService->findOrCreateAlbum($artistName, $albumTitle);

        return new JsonResponse([
            'id' => $album->getId(),
            'title' => $album->getTitle(),
            'artist' => $album->getArtist()?->getName(),
            'genre' => $album->getGenre()?->getName(),
            'tracks' => array_map(static fn ($track): array => [
                'title' => $track->getTitle(),
                'trackNumber' => $track->getTrackNumber(),
                'duration' => $track->getDuration(),
            ], $album->getTracks()->toArray()),
        ]);
    }
}

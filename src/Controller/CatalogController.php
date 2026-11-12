<?php

namespace App\Controller;

use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\GenreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\MusicCatalogService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CatalogController extends AbstractController
{
    #[Route('/catalog', name: 'catalog_search', methods: ['GET'])]
    public function search(Request $request, AlbumRepository $albumRepository, GenreRepository $genreRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $genreValue = $request->query->all()['genre'] ?? null;
        $genreId = is_string($genreValue) && ctype_digit($genreValue) && (int) $genreValue > 0
            ? (int) $genreValue
            : null;
        $yearValue = trim((string) $request->query->get('year', ''));
        $year = ctype_digit($yearValue) && (int) $yearValue >= 1900 && (int) $yearValue <= 2100
            ? (int) $yearValue
            : null;

        return $this->render('catalog/index.html.twig', [
            'albums' => $albumRepository->search($query, $genreId, $year),
            'genres' => $genreRepository->findBy([], ['name' => 'ASC']),
            'query' => $query,
            'selected_genre' => $genreId,
            'selected_year' => $yearValue,
        ]);
    }

    #[Route('/catalog/album/{id}', name: 'catalog_album_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, AlbumRepository $albumRepository): Response
    {
        $album = $albumRepository->find($id);
        if ($album === null) {
            throw $this->createNotFoundException('Album introuvable.');
        }

        $tracks = $album->getTracks()->toArray();
        usort($tracks, static fn ($left, $right): int => ($left->getTrackNumber() ?? 0) <=> ($right->getTrackNumber() ?? 0));

        return $this->render('catalog/detail.html.twig', [
            'album' => $album,
            'tracks' => $tracks,
        ]);
    }

    #[Route('/api/catalog/autocomplete', name: 'api_catalog_autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request, AlbumRepository $albumRepository, ArtistRepository $artistRepository): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $type = (string) $request->query->get('type', 'all');
        if (!in_array($type, ['all', 'album', 'artist'], true)) {
            throw new BadRequestHttpException('Le type d’autocomplétion est invalide.');
        }

        $results = [];
        if ($type !== 'artist') {
            foreach (($type === 'album' ? $albumRepository->findAlbumsForAutocomplete($query) : $albumRepository->findForAutocomplete($query)) as $album) {
                $results[] = [
                    'label' => $album->getTitle(),
                    'type' => 'Album',
                    'artist' => $album->getArtist()?->getName(),
                    'url' => $this->generateUrl('catalog_search', ['q' => $album->getTitle()]),
                ];
            }
        }

        if ($type !== 'album') {
            foreach ($artistRepository->findForAutocomplete($query, $type === 'artist' ? 8 : 5) as $artist) {
                $results[] = [
                    'label' => $artist->getName(),
                    'type' => 'Artiste',
                    'artist' => null,
                    'url' => $this->generateUrl('catalog_search', ['q' => $artist->getName()]),
                ];
            }
        }

        return new JsonResponse(array_slice($results, 0, 8));
    }

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
            'label' => $album->getLabel(),
            'tracks' => array_map(static fn ($track): array => [
                'title' => $track->getTitle(),
                'trackNumber' => $track->getTrackNumber(),
                'duration' => $track->getDuration(),
            ], $album->getTracks()->toArray()),
        ]);
    }
}

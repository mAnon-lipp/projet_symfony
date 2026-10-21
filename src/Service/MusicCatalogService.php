<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Genre;
use App\Entity\Track;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MusicCatalogService
{
    public function __construct(
        private readonly MusicBrainzClient $musicBrainzClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ArtistRepository $artistRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly GenreRepository $genreRepository,
    ) {
    }

    public function findOrCreateAlbum(string $artistName, string $albumTitle): Album
    {
        $artist = $this->findOrCreateArtist($artistName);
        $album = $this->albumRepository->findOneByTitleAndArtist($albumTitle, $artist);

        if ($album instanceof Album) {
            return $album;
        }

        $release = $this->musicBrainzClient->findRelease($albumTitle, $artistName);
        $album = new Album();
        $album->setTitle((string) ($release['title'] ?? $albumTitle));
        $album->setArtist($artist);
        $album->setReleaseDate($this->parseReleaseDate($release['date'] ?? null));
        $album->setCoverUrl('');
        $album->setCreatedAt(new \DateTimeImmutable());
        $album->setGenre($this->findOrCreateGenre($this->extractGenre($release)));

        $this->entityManager->persist($album);

        foreach ($this->extractTracks($release) as $trackData) {
            $track = new Track();
            $track->setAlbum($album);
            $track->setTitle($trackData['title']);
            $track->setTrackNumber($trackData['number']);
            $track->setDuration($trackData['duration']);
            $track->setExplicit(false);
            $this->entityManager->persist($track);
        }

        $this->entityManager->flush();

        return $album;
    }

    private function findOrCreateArtist(string $name): Artist
    {
        $artist = $this->artistRepository->findOneByNormalizedName($name);
        if ($artist instanceof Artist) {
            return $artist;
        }

        $result = $this->musicBrainzClient->findArtist($name);
        if ($result === null || !isset($result['name'])) {
            throw new \RuntimeException(sprintf('Artiste "%s" introuvable dans MusicBrainz.', $name));
        }

        $artist = new Artist();
        $artist->setName((string) $result['name']);
        $artist->setImageUrl('');
        $artist->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($artist);

        return $artist;
    }

    private function findOrCreateGenre(string $name): Genre
    {
        $genre = $this->genreRepository->findOneByNormalizedName($name);
        if ($genre instanceof Genre) {
            return $genre;
        }

        $genre = new Genre();
        $genre->setName($name);
        $this->entityManager->persist($genre);

        return $genre;
    }

    private function parseReleaseDate(mixed $date): \DateTimeImmutable
    {
        if (is_string($date)) {
            if (preg_match('/^\d{4}$/', $date) === 1) {
                return new \DateTimeImmutable($date.'-01-01');
            }

            if (preg_match('/^\d{4}-\d{2}$/', $date) === 1) {
                return new \DateTimeImmutable($date.'-01');
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                return new \DateTimeImmutable($date);
            }
        }

        throw new \RuntimeException('MusicBrainz ne fournit pas de date de sortie exploitable.');
    }

    private function extractGenre(array $release): string
    {
        $genre = $release['genres'][0]['name'] ?? null;

        return is_string($genre) && trim($genre) !== '' ? trim($genre) : 'Unknown';
    }

    /**
     * @return list<array{title: string, number: int, duration: int}>
     */
    private function extractTracks(array $release): array
    {
        $tracks = [];
        foreach ($release['media'] ?? [] as $medium) {
            foreach ($medium['tracks'] ?? [] as $track) {
                if (!is_array($track) || !isset($track['title'])) {
                    continue;
                }

                $tracks[] = [
                    'title' => (string) $track['title'],
                    'number' => (int) ($track['position'] ?? count($tracks) + 1),
                    'duration' => max(0, (int) round(((int) ($track['length'] ?? 0)) / 1000)),
                ];
            }
        }

        return $tracks;
    }
}

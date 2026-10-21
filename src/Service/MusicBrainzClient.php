<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class MusicBrainzClient
{
    private const API_URL = 'https://musicbrainz.org/ws/2';
    private const COVER_ART_API_URL = 'https://coverartarchive.org';
    private const USER_AGENT = 'ProjetSymfony/1.0 (music catalog application)';
    private const MAX_ATTEMPTS = 3;
    private ?float $lastRequestAt = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findArtist(string $name): ?array
    {
        $data = $this->request('/artist', [
            'query' => sprintf('artist:"%s"', $name),
            'limit' => 5,
        ]);

        foreach ($data['artists'] ?? [] as $artist) {
            if (is_array($artist) && strcasecmp(trim((string) ($artist['name'] ?? '')), trim($name)) === 0) {
                return $artist;
            }
        }

        return isset($data['artists'][0]) && is_array($data['artists'][0])
            ? $data['artists'][0]
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function findRelease(string $albumTitle, string $artistName): array
    {
        $data = $this->request('/release', [
            'query' => sprintf('release:"%s" AND artist:"%s"', $albumTitle, $artistName),
            'limit' => 1,
        ]);
        $release = $data['releases'][0] ?? null;

        if (!is_array($release) || !isset($release['id'])) {
            throw new \RuntimeException(sprintf('Album "%s" introuvable dans MusicBrainz.', $albumTitle));
        }

        return $this->request('/release/'.rawurlencode((string) $release['id']), [
            'inc' => 'recordings+artist-credits+genres+tags+release-groups',
        ]) + [
            'coverUrl' => $this->findCoverUrl((string) $release['id']),
        ];
    }

    private function findCoverUrl(string $releaseId): ?string
    {
        try {
            $data = $this->request('/release/'.rawurlencode($releaseId), [], self::COVER_ART_API_URL);
        } catch (\RuntimeException) {
            return null;
        }

        $image = $data['images'][0] ?? null;
        if (!is_array($image)) {
            return null;
        }

        $thumbnail = $image['thumbnails']['500'] ?? $image['image'] ?? null;

        return is_string($thumbnail) && $thumbnail !== '' ? $thumbnail : null;
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $path, array $query, string $baseUrl = self::API_URL): array
    {
        if ($this->lastRequestAt !== null) {
            $elapsed = microtime(true) - $this->lastRequestAt;
            if ($elapsed < 1.0) {
                usleep((int) ((1.0 - $elapsed) * 1_000_000));
            }
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $response = $this->httpClient->request('GET', $baseUrl.$path, [
                    'headers' => [
                        'Accept' => 'application/json',
                        'User-Agent' => self::USER_AGENT,
                    ],
                    'query' => [...$query, 'fmt' => 'json'],
                ]);
                $this->lastRequestAt = microtime(true);
                $statusCode = $response->getStatusCode();

                if ($statusCode === 429 || $statusCode >= 500) {
                    if ($attempt < self::MAX_ATTEMPTS) {
                        sleep($attempt);
                        continue;
                    }

                    throw new \RuntimeException('MusicBrainz est temporairement indisponible. Réessayez dans quelques instants.');
                }

                $data = $response->toArray();
                if (!is_array($data)) {
                    throw new \RuntimeException('MusicBrainz a retourné une réponse invalide.');
                }

                return $data;
            } catch (TransportExceptionInterface $exception) {
                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep($attempt);
                    continue;
                }

                throw new \RuntimeException('MusicBrainz est momentanément inaccessible. Réessayez dans quelques instants.', 0, $exception);
            } catch (ClientExceptionInterface $exception) {
                throw new \RuntimeException('MusicBrainz a refusé cette requête. Vérifiez les informations de l’album.', 0, $exception);
            }
        }

        throw new \LogicException('MusicBrainz request loop ended unexpectedly.');
    }
}

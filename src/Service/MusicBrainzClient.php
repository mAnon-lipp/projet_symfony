<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MusicBrainzClient
{
    private const API_URL = 'https://musicbrainz.org/ws/2';
    private const USER_AGENT = 'ProjetSymfony/1.0 (music catalog application)';
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
            'inc' => 'recordings+artist-credits+genres',
        ]);
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        if ($this->lastRequestAt !== null) {
            $elapsed = microtime(true) - $this->lastRequestAt;
            if ($elapsed < 1.0) {
                usleep((int) ((1.0 - $elapsed) * 1_000_000));
            }
        }

        $response = $this->httpClient->request('GET', self::API_URL.$path, [
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
            'query' => [...$query, 'fmt' => 'json'],
        ]);
        $this->lastRequestAt = microtime(true);
        $data = $response->toArray();

        if (!is_array($data)) {
            throw new \RuntimeException('MusicBrainz a retourné une réponse invalide.');
        }

        return $data;
    }
}

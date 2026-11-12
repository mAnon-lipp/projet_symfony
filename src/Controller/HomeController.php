<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Review;
use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(AlbumRepository $albumRepository, ReviewRepository $reviewRepository): Response
    {
        $featuredAlbum = $albumRepository->findLatest()[0] ?? null;
        $featuredReviews = $featuredAlbum instanceof Album
            ? array_values(array_filter(
                $featuredAlbum->getReviews()->toArray(),
                static fn (Review $review): bool => $review->getDeletedAt() === null && $review->getRating() !== null,
            ))
            : [];
        $featuredRating = $featuredReviews === []
            ? null
            : round(array_sum(array_map(static fn (Review $review): int => (int) $review->getRating(), $featuredReviews)) / count($featuredReviews), 1);

        return $this->render('home/index.html.twig', [
            'featured_album' => $featuredAlbum,
            'featured_rating' => $featuredRating,
            'featured_review_count' => count($featuredReviews),
            'community_reviews' => $reviewRepository->findRecentPublic(),
            'popular_albums' => $albumRepository->findMostReviewed(),
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Album;
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
        if ($this->getUser() === null) {
            return $this->render('home/landing.html.twig', [
                'community_reviews' => $reviewRepository->findRecentPublic(),
            ]);
        }

        $featuredAlbum = $albumRepository->findLatest()[0] ?? null;
        $featuredRating = null;
        $featuredReviewCount = 0;

        if ($featuredAlbum instanceof Album) {
            $ratingSummary = $reviewRepository->getRatingSummaryForAlbum($featuredAlbum);
            $featuredRating = $ratingSummary['average'] !== null
                ? round($ratingSummary['average'], 1)
                : null;
            $featuredReviewCount = $ratingSummary['count'];
        }

        return $this->render('home/index.html.twig', [
            'featured_album' => $featuredAlbum,
            'featured_rating' => $featuredRating,
            'featured_review_count' => $featuredReviewCount,
            'community_reviews' => $reviewRepository->findRecentPublic(),
            'popular_albums' => $albumRepository->findMostReviewed(),
        ]);
    }
}

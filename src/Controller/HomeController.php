<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_diary');
        }

        return $this->render('home/index.html.twig', [
            'community_lists' => [
                [
                    'cover' => 'community-1.jpg',
                    'title' => 'Fetch the Bolt Cutters',
                    'artist' => 'Fiona Apple',
                    'user' => 'mara_t',
                    'time' => '2h ago',
                    'rating' => 4,
                    'description' => 'Absolutely unhinged in the best possible way. Every listen reveals something new.',
                    'avatar' => 'avatar-1.jpg',
                ],
                [
                    'cover' => 'community-2.jpg',
                    'title' => 'To Pimp a Butterfly',
                    'artist' => 'Kendrick Lamar',
                    'user' => 'joels',
                    'time' => '4h ago',
                    'rating' => 5,
                    'description' => 'A jazz-rap monument. The production is so alive it breathes.',
                    'avatar' => 'avatar-2.jpg',
                ],
                [
                    'cover' => 'community-3.jpg',
                    'title' => 'Blue',
                    'artist' => 'Joni Mitchell',
                    'user' => 'cleo.w',
                    'time' => '6h ago',
                    'rating' => 5,
                    'description' => 'The most honest record ever made. Still cried on track 4.',
                    'avatar' => 'avatar-3.jpg',
                ],
                [
                    'cover' => 'community-4.jpg',
                    'title' => 'Discovery',
                    'artist' => 'Daft Punk',
                    'user' => 'renae.d',
                    'time' => '8h ago',
                    'rating' => 4,
                    'description' => 'Instant nostalgia and bright, unforgettable hooks.',
                    'avatar' => 'avatar-4.jpg',
                ],
            ],
            'popular_albums' => [
                ['cover' => 'popular-1.jpg', 'title' => 'GUTS', 'artist' => 'Olivia Rodrigo'],
                ['cover' => 'popular-2.jpg', 'title' => 'Javelin', 'artist' => 'Sufjan Stevens'],
                ['cover' => 'popular-3.jpg', 'title' => 'Ocean Blvd', 'artist' => 'Lana Del Rey'],
                ['cover' => 'popular-4.jpg', 'title' => 'the record', 'artist' => 'boygenius'],
                ['cover' => 'popular-5.jpg', 'title' => 'Scaring the Hoes', 'artist' => 'JPEGMAFIA & Danny Brown'],
                ['cover' => 'popular-6.jpg', 'title' => 'Desire, I Want to Turn', 'artist' => 'Caroline Polachek'],
                ['cover' => 'popular-7.jpg', 'title' => 'Endless Summer Vacation', 'artist' => 'Miley Cyrus'],
                ['cover' => 'popular-8.jpg', 'title' => 'Bewitched', 'artist' => 'Laufey'],
                ['cover' => 'popular-9.jpg', 'title' => 'Guts (spilled)', 'artist' => 'Olivia Rodrigo'],
                ['cover' => 'popular-10.jpg', 'title' => "Tiger's Blood", 'artist' => 'Waxahatchee'],
            ],
        ]);
    }
}

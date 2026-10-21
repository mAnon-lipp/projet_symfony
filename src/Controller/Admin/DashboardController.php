<?php

namespace App\Controller\Admin;

use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\GenreRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly ArtistRepository $artistRepository,
        private readonly GenreRepository $genreRepository,
        private readonly ReviewRepository $reviewRepository,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users' => $this->userRepository->count([]),
                'albums' => $this->albumRepository->count([]),
                'artists' => $this->artistRepository->count([]),
                'genres' => $this->genreRepository->count([]),
                'reviews' => $this->reviewRepository->count([]),
            ],
            'recentUsers' => $this->userRepository->findBy([], ['created_at' => 'DESC'], 8),
            'recentReviews' => $this->reviewRepository->findBy([], ['created_at' => 'DESC'], 8),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('App');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users');
        yield MenuItem::linkTo(ArtistCrudController::class, 'Artists', 'fa fa-user');
        yield MenuItem::linkTo(GenreCrudController::class, 'Genres', 'fa fa-tags');
        yield MenuItem::linkTo(AlbumCrudController::class, 'Albums', 'fa fa-music');
        yield MenuItem::linkTo(ReviewCrudController::class, 'Reviews', 'fa fa-star');
    }
}

<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Service\MusicCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DiaryController extends AbstractController
{
    #[Route('/diary', name: 'app_diary', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        ReviewRepository $reviewRepository,
        MusicCatalogService $catalogService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $formErrors = [];
        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $artist = trim((string) $payload->get('artist'));
            $albumTitle = trim((string) $payload->get('album'));
            $listenedAtValue = trim((string) $payload->get('listened_at'));

            if (!$this->isCsrfTokenValid('diary_entry', (string) $payload->get('_token'))) {
                $formErrors[] = 'Your session expired. Please try again.';
            }

            $listenedAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $listenedAtValue);
            $dateErrors = \DateTimeImmutable::getLastErrors();
            if ($artist === '' || $albumTitle === '') {
                $formErrors[] = 'Enter both an artist and an album.';
            }
            if ($listenedAt === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                $formErrors[] = 'Choose a valid listening date.';
            }

            if ($formErrors === [] && $listenedAt instanceof \DateTimeImmutable) {
                try {
                    $album = $catalogService->findOrCreateAlbum($artist, $albumTitle);
                } catch (\RuntimeException $exception) {
                    $formErrors[] = $exception->getMessage();
                }
            }

            if ($formErrors === [] && $listenedAt instanceof \DateTimeImmutable) {
                $review = new Review();
                $review->setUserId($user);
                $review->setAlbumId($album);
                $review->setRating(0);
                $review->setComment('');
                $review->setListenedAt($listenedAt);
                $review->setIsFavorite(false);
                $review->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($review);
                $entityManager->flush();

                $this->addFlash('success', 'Album added to your diary.');

                return $this->redirectToRoute('app_diary');
            }
        }

        return $this->render('diary/index.html.twig', [
            'entries' => $reviewRepository->findBy(
                ['user_id' => $user],
                ['listened_at' => 'DESC', 'id' => 'DESC'],
            ),
            'form_errors' => $formErrors,
            'form_values' => [
                'artist' => (string) ($request->getPayload()->get('artist') ?? ''),
                'album' => (string) ($request->getPayload()->get('album') ?? ''),
                'listened_at' => (string) ($request->getPayload()->get('listened_at') ?? (new \DateTimeImmutable())->format('Y-m-d')),
            ],
        ]);
    }
}

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
            $ratingValue = trim((string) $payload->get('rating'));
            $comment = trim((string) $payload->get('comment'));
            $isFavorite = $payload->get('is_favorite') !== null;

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
            if (!in_array($ratingValue, ['1', '2', '3', '4', '5'], true)) {
                $formErrors[] = 'Choose a rating from 1 to 5 stars.';
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
                $review->setRating((int) $ratingValue);
                $review->setComment($comment);
                $review->setListenedAt($listenedAt);
                $review->setIsFavorite($isFavorite);
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
                'rating' => (string) ($request->getPayload()->get('rating') ?? ''),
                'comment' => (string) ($request->getPayload()->get('comment') ?? ''),
                'is_favorite' => $request->getPayload()->get('is_favorite') !== null,
            ],
        ]);
    }

    #[Route('/diary/{id}/edit', name: 'app_diary_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        int $id,
        Request $request,
        ReviewRepository $reviewRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $review = $reviewRepository->findOneBy(['id' => $id, 'user_id' => $user]);
        if (!$review instanceof Review) {
            throw $this->createNotFoundException('Listening entry not found.');
        }

        $formErrors = [];
        $ratingValue = (string) $review->getRating();
        $comment = $review->getComment() ?? '';
        $isFavorite = $review->isFavorite() ?? false;

        if ($request->isMethod('POST')) {
            $payload = $request->getPayload();
            $ratingValue = trim((string) $payload->get('rating'));
            $comment = trim((string) $payload->get('comment'));
            $isFavorite = $payload->get('is_favorite') !== null;

            if (!$this->isCsrfTokenValid('diary_edit_'.$review->getId(), (string) $payload->get('_token'))) {
                $formErrors[] = 'Your session expired. Please try again.';
            }
            if (!in_array($ratingValue, ['1', '2', '3', '4', '5'], true)) {
                $formErrors[] = 'Choose a rating from 1 to 5 stars.';
            }

            if ($formErrors === []) {
                $review
                    ->setRating((int) $ratingValue)
                    ->setComment($comment)
                    ->setIsFavorite($isFavorite)
                    ->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();

                $this->addFlash('success', 'Diary entry updated.');

                return $this->redirectToRoute('app_diary');
            }
        }

        return $this->render('diary/edit.html.twig', [
            'entry' => $review,
            'form_errors' => $formErrors,
            'form_values' => [
                'rating' => $ratingValue,
                'comment' => $comment,
                'is_favorite' => $isFavorite,
            ],
        ]);
    }
}

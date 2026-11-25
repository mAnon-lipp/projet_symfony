<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Review;
use App\Entity\User;
use App\Form\DiaryEntryType;
use App\Repository\ReviewRepository;
use App\Service\MusicCatalogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DiaryController extends AbstractController
{
    private const ENTRIES_PER_PAGE = 10;

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

        $review = (new Review())
            ->setUserId($user)
            ->setListenedAt(new \DateTimeImmutable())
            ->setComment('')
            ->setIsFavorite(false);
        $form = $this->createForm(DiaryEntryType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $album = null;
            try {
                $album = $catalogService->findOrCreateAlbum(
                    (string) $form->get('artist')->getData(),
                    (string) $form->get('album')->getData(),
                );
            } catch (\RuntimeException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }

            if ($album instanceof Album) {
                $review
                    ->setAlbumId($album)
                    ->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($review);
                $entityManager->flush();

                $this->addFlash('success', 'Album added to your diary.');

                return $this->redirectToRoute('app_diary');
            }
        }

        $maxPage = intdiv(PHP_INT_MAX, self::ENTRIES_PER_PAGE) + 1;
        $page = min($maxPage, max(1, $request->query->getInt('page', 1)));
        $entries = $reviewRepository->paginateForUser($user, $page, self::ENTRIES_PER_PAGE);
        $totalEntries = count($entries);
        $totalPages = max(1, (int) ceil($totalEntries / self::ENTRIES_PER_PAGE));

        if ($page > $totalPages) {
            return $this->redirectToRoute(
                'app_diary',
                $totalPages > 1 ? ['page' => $totalPages] : [],
            );
        }

        return $this->render('diary/index.html.twig', [
            'entries' => $entries,
            'total_entries' => $totalEntries,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'diary_form' => $form,
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

        $form = $this->createForm(DiaryEntryType::class, $review, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Diary entry updated.');

            return $this->redirectToRoute('app_diary');
        }

        return $this->render('diary/edit.html.twig', [
            'entry' => $review,
            'diary_form' => $form,
        ]);
    }
}

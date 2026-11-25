<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserProfileFormType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UserController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function profile(
        Request $request,
        ReviewRepository $reviewRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $profileForm = $this->createForm(UserProfileFormType::class, $user);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $avatar = $profileForm->get('avatar')->getData();
            if ($avatar instanceof UploadedFile) {
                $filename = 'avatar-'.bin2hex(random_bytes(12)).'.'.$avatar->guessExtension();
                $avatar->move($this->getParameter('kernel.project_dir').'/public/uploads/avatars', $filename);
                $user->setAvatarUrl($filename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profile updated.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user/profile.html.twig', [
            'profile_user' => $user,
            'profile_form' => $profileForm,
            'favorite_entries' => $reviewRepository->findFavoriteAlbumsForUser($user),
            'recent_entries' => $reviewRepository->findRecentForUser($user),
        ]);
    }
}
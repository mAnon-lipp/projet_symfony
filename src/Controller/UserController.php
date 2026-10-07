<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * GET /profil
     * Returns the authenticated user's profile.
     */
    #[Route('/profil', name: 'profil_get', methods: ['GET'])]
    public function getProfil(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id'               => $user->getId(),
            'email'            => $user->getEmail(),
            'username'         => $user->getUsername(),
            'avatar_url'       => $user->getAvatarUrl(),
            'bio'              => $user->getBio(),
            'is_verified'   => $user->getVerifiedAt() !== null,
            'cgu_accepted'  => $user->getCguValidatedAt() !== null,
        ]);
    }

    /**
     * PATCH /profil
     * Updates the authenticated user's profile (email, username, avatar_url, bio).
     */
    #[Route('/profil', name: 'profil_update', methods: ['PATCH'])]
    public function updateProfil(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return $this->json(['message' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }

        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        if (array_key_exists('avatar_url', $data)) {
            $user->setAvatarUrl($data['avatar_url']);
        }

        if (array_key_exists('bio', $data)) {
            $user->setBio($data['bio']);
        }

        $this->em->flush();

        return $this->json(['message' => 'Profile updated successfully.']);
    }

    /**
     * PATCH /profil/password
     * Updates the authenticated user's password.
     * Expects: { "current_password": "...", "new_password": "..." }
     */
    #[Route('/profil/password', name: 'profil_update_password', methods: ['PATCH'])]
    public function updatePassword(#[CurrentUser] ?User $user, Request $request): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if ($data === null) {
            return $this->json(['message' => 'Invalid JSON.'], Response::HTTP_BAD_REQUEST);
        }

        $currentPassword = $data['current_password'] ?? null;
        $newPassword     = $data['new_password'] ?? null;

        if (!$currentPassword || !$newPassword) {
            return $this->json(
                ['message' => 'Fields "current_password" and "new_password" are required.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['message' => 'Current password is incorrect.'], Response::HTTP_BAD_REQUEST);
        }

        $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashed);
        $this->em->flush();

        return $this->json(['message' => 'Password updated successfully.']);
    }

    /**
     * DELETE /profil
     * Soft-deletes the authenticated user's account.
     */
    #[Route('/profil', name: 'profil_delete', methods: ['DELETE'])]
    public function deleteProfil(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        $user->setDeletedAt(new \DateTimeImmutable());
        $user->setDeletedBy($user->getEmail());
        $this->em->flush();

        return $this->json(['message' => 'Account deleted successfully.'], Response::HTTP_OK);
    }

    /**
     * POST /profil/accept-cgu
     * Marks the CGU as accepted for the authenticated user.
     */
    #[Route('/profil/accept-cgu', name: 'profil_accept_cgu', methods: ['POST'])]
    public function acceptCgu(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['message' => 'Not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getCguValidatedAt() !== null) {
            return $this->json(['message' => 'CGU already accepted.'], Response::HTTP_OK);
        }

        $user->setCguValidatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json(['message' => 'CGU accepted successfully.'], Response::HTTP_OK);
    }
}

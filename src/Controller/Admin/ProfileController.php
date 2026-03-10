<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/profile')]
#[IsGranted('ROLE_ADMIN')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_admin_profile')]
    public function index(): Response
    {
        return $this->render('admin/profile/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/change-password', name: 'app_admin_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        // Get the form data
        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validate the form
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->addFlash('error', 'All fields are required.');
            return $this->redirectToRoute('app_admin_profile');
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'The new password must be at least 6 characters long.');
            return $this->redirectToRoute('app_admin_profile');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'New passwords do not match.');
            return $this->redirectToRoute('app_admin_profile');
        }

        // Check if current password is correct
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('app_admin_profile');
        }

        // Update the password
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Your password has been changed successfully!');
        return $this->redirectToRoute('app_admin_profile');
    }
}

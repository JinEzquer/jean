<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {
    }

    #[Route('/dashboard', name: 'staff_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $user = $this->getUser();
        
        return $this->render('staff/dashboard.html.twig', [
            'user' => $user,
            'page_title' => 'Staff Dashboard',
        ]);
    }

    #[Route('/profile', name: 'staff_profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('staff/profile.html.twig', [
            'user' => $this->getUser(),
            'page_title' => 'My Profile',
        ]);
    }

    #[Route('/change-password', name: 'staff_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('staff_profile');
        }

        // Handle GET request - show the form
        if ($request->isMethod('GET')) {
            return $this->render('staff/change_password.html.twig', [
                'user' => $user,
                'page_title' => 'Change Password',
            ]);
        }
        
        // Handle POST request - process the form
        // Get the form data
        $currentPassword = $request->request->get('current_password');
        $newPassword = $request->request->get('new_password');
        $confirmPassword = $request->request->get('confirm_password');

        // Validate the form
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->addFlash('error', 'All fields are required.');
            return $this->redirectToRoute('staff_change_password');
        }

        if (strlen($newPassword) < 6) {
            $this->addFlash('error', 'The new password must be at least 6 characters long.');
            return $this->redirectToRoute('staff_change_password');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'New passwords do not match.');
            return $this->redirectToRoute('staff_change_password');
        }

        // Check if current password is correct
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('staff_change_password');
        }

        // Update the password
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Your password has been changed successfully!');
        return $this->redirectToRoute('staff_profile');
    }
}


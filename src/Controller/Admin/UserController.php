<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private ActivityLogger $activityLogger;

    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->activityLogger = $activityLogger;
    }

    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->userRepository->findAll(),
            'page_title' => 'User Management',
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Log the user creation
            $this->activityLogger->logUserCreated($user, $this->getUser());

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'page_title' => 'Create New User',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        // Don't allow editing the currently logged-in user's role
        $isCurrentUser = $user->getId() === $this->getUser()->getId();
        
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'is_current_user' => $isCurrentUser
        ]);
        
        $originalData = [
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive()
        ];

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password change if provided
            if ($form->get('plainPassword')->getData()) {
                $user->setPassword(
                    $this->passwordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );
            }

            $this->entityManager->flush();

            // Log changes
            $newData = [
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive()
            ];
            
            $changes = [];
            foreach ($newData as $key => $value) {
                if ($originalData[$key] != $value) {
                    $changes[$key] = [
                        'old' => $originalData[$key],
                        'new' => $value
                    ];
                }
            }

            if (!empty($changes)) {
                $this->activityLogger->logUserUpdated($user, $changes, $this->getUser());
            }

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'page_title' => 'Edit User',
            'is_current_user' => $isCurrentUser,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('admin_user_index');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            // Log before deletion
            $this->activityLogger->logUserDeleted($user, $this->getUser());
            
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user): Response
    {
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot disable your own account.');
            return $this->redirectToRoute('admin_user_index');
        }

        if ($this->isCsrfTokenValid('toggle' . $user->getId(), $request->request->get('_token'))) {
            $newStatus = !$user->isActive();
            $user->setIsActive($newStatus);
            
            $this->entityManager->flush();

            // Log the status change
            $this->activityLogger->logUserUpdated($user, [
                'isActive' => [
                    'old' => !$newStatus,
                    'new' => $newStatus
                ]
            ], $this->getUser());

            $statusText = $newStatus ? 'enabled' : 'disabled';
            $this->addFlash('success', "User has been {$statusText} successfully.");
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/change-password', name: 'admin_user_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, User $user): Response
    {
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validate the form
            if (empty($newPassword) || empty($confirmPassword)) {
                $this->addFlash('error', 'All fields are required.');
                return $this->redirectToRoute('admin_user_index');
            }

            if (strlen($newPassword) < 6) {
                $this->addFlash('error', 'The new password must be at least 6 characters long.');
                return $this->redirectToRoute('admin_user_index');
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'New passwords do not match.');
                return $this->redirectToRoute('admin_user_index');
            }

            // Update the password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            $this->entityManager->flush();

            // Log the password change
            $this->activityLogger->logUserUpdated($user, [
                'password' => [
                    'old' => '***',
                    'new' => '***'
                ]
            ], $this->getUser());

            $this->addFlash('success', "Password for {$user->getEmail()} has been changed successfully.");
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/change_password.html.twig', [
            'user' => $user,
            'page_title' => 'Change Password',
        ]);
    }
}

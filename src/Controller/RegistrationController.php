<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        // Allow registration only if not logged in
        if ($this->getUser()) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Check if email already exists
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
                if ($existingUser) {
                    $this->addFlash('error', 'An account with this email already exists. Please use a different email or try logging in.');
                    // Recreate form to get fresh CSRF token
                    $user = new User();
                    $form = $this->createForm(RegistrationFormType::class, $user);
                    return $this->render('registration/register.html.twig', [
                        'registrationForm' => $form->createView(),
                    ]);
                }

                // Encode the plain password
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );

                // Set user as active and assign default role
                $user->setIsActive(true);
                $user->setRoles(['ROLE_USER']);

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Registration successful! You can now log in.');
                return $this->redirectToRoute('app_login');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->addFlash('error', 'An account with this email already exists. Please use a different email or try logging in.');
                // Recreate form to get fresh CSRF token
                $user = new User();
                $form = $this->createForm(RegistrationFormType::class, $user);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Registration failed: ' . $e->getMessage());
                // Recreate form to get fresh CSRF token
                $user = new User();
                $form = $this->createForm(RegistrationFormType::class, $user);
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}

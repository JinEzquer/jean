<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SecurityController extends AbstractController
{
    use TargetPathTrait;

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Ensure session is started for CSRF token generation
        $session = $request->getSession();
        $session->start();

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Store the target URL to redirect to after login
        if ($targetPath = $this->getTargetPath($session, 'main')) {
            $this->addFlash('info', 'Please log in to access that page.');
        } elseif ($error) {
            $this->addFlash('error', $error->getMessageKey());
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error,
            'target_path' => $targetPath ?? null,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        // Controller can be blank: it will never be called!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }

    /**
     * Redirect users after login based on their role
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        
        if ($user instanceof User) {
            if ($user->isAdmin()) {
                return new RedirectResponse($this->generateUrl('app_admin'));
            } elseif ($user->isStaff()) {
                return new RedirectResponse($this->generateUrl('staff_dashboard'));
            }
        }

        // Redirect to the target URL if it exists
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        // Default redirect for regular users
        return new RedirectResponse($this->generateUrl('user_dashboard'));
    }
}

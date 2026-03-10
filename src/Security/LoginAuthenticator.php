<?php

namespace App\Security;

use App\Entity\User;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public function __construct(
        private EntityManagerInterface $entityManager, 
        private RouterInterface $router,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AuthenticationUtils $authenticationUtils,
        private Security $security,
        private ActivityLogger $activityLogger
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'app_login' === $request->attributes->get('_route') 
            && $request->isMethod('POST')
            && $request->request->has('email')
            && $request->request->has('password');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $csrfToken = $request->request->get('_csrf_token');

        // Ensure session is started for CSRF token validation
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        // Store the last username in session
        $session->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email, function($userIdentifier) {
                $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new UserNotFoundException('Email could not be found.');
                }

                if (!$user->getIsActive()) {
                    throw new CustomUserMessageAuthenticationException('Your account has been deactivated.');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Update last login time
        $user = $token->getUser();
        if ($user instanceof User) {
            $user->setLastLogin(new \DateTimeImmutable());
            $this->entityManager->flush();
            
            // Log login activity
            $this->activityLogger->logLogin($user);
        }

        // Redirect to the target path if it exists, but only if user has access
        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            // Check if the target path is an admin route and user is not admin
            if (str_starts_with($targetPath, '/admin') && !$user->isAdmin()) {
                // Clear the target path for staff users trying to access admin routes
                $this->removeTargetPath($request->getSession(), $firewallName);
            } else {
                // User has access to the target path, redirect there
                return new RedirectResponse($targetPath);
            }
        }

        // Redirect to appropriate dashboard based on role
        if ($user->isAdmin()) {
            return new RedirectResponse($this->router->generate('app_admin'));
        } elseif ($user->isStaff()) {
            // If staff dashboard route doesn't exist, redirect to home
            try {
                return new RedirectResponse($this->router->generate('staff_dashboard'));
            } catch (\Exception $e) {
                return new RedirectResponse($this->router->generate('app_home'));
            }
        }

        // Default redirect if no specific role is matched
        return new RedirectResponse($this->router->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set('_security.last_error', $exception);
            $request->getSession()->set('_security.last_username', $request->request->get('email'));
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate('app_login');
    }
}

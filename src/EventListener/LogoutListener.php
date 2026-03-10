<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\ActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class LogoutListener implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if ($token && $token->getUser() instanceof User) {
            $user = $token->getUser();
            $this->activityLogger->logLogout($user);
        }
    }
}


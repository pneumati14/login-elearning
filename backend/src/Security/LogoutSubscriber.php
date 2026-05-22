<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * The logout firewall would redirect by default; for the SPA we answer
 * the POST /api/logout with a small JSON acknowledgement instead.
 */
final class LogoutSubscriber
{
    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $event->setResponse(new JsonResponse(['status' => 'logged_out']));
    }
}

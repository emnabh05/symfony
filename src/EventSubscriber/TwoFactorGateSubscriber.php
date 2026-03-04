<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TwoFactorGateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        if ($route === '' || str_starts_with($route, '_')) {
            return;
        }

        $allowedRoutes = [
            'app_2fa_check',
            'app_logout',
            'app_login',
            'app_login_avatar',
        ];
        if (in_array($route, $allowedRoutes, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $secret = (string) ($user->getTwoFactorSecret() ?? '');
        if (!$user->isTwoFactorEnabled() || $secret === '') {
            return;
        }

        $session = $request->getSession();
        $verified = (bool) $session->get('2fa_verified', false);
        $pendingId = (int) $session->get('2fa_pending_user_id', 0);

        if ($verified && $pendingId === 0) {
            return;
        }

        $session->set('2fa_pending_user_id', (int) $user->getId());
        $session->set('2fa_verified', false);
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_2fa_check')));
    }
}

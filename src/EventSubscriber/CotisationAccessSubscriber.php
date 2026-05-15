<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CotisationAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = (string) $request->attributes->get('_route', '');
        $pathInfo = $request->getPathInfo();

        if (!$this->isCotisationProtectedRoute($routeName, $pathInfo)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isCotisationPayee()) {
            return;
        }

        $request->getSession()?->getFlashBag()->add('warning', 'Tu dois régler ta cotisation pour accéder aux pronostics.');

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_account')));
    }

    private function isCotisationProtectedRoute(string $routeName, string $pathInfo): bool
    {
        if ('app_pronostics' === $routeName) {
            return true;
        }

        if (str_starts_with($routeName, 'app_pronostic_')) {
            return true;
        }

        return str_starts_with($pathInfo, '/pronostic') || str_starts_with($pathInfo, '/pronostics');
    }
}

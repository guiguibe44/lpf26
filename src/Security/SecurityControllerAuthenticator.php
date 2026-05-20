<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SecurityControllerAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->getString('email');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->getString('password')),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $targetPath = $this->getTargetPath($session, $firewallName);

        if (null !== $targetPath) {
            // Toujours consommer la session : sinon une ancienne cible (ex. URL d’API) se réapplique à chaque login.
            $this->removeTargetPath($session, $firewallName);
            if ($this->isSafeRedirectAfterLogin($request, $targetPath)) {
                return new RedirectResponse($targetPath);
            }
        }

        // Admins et joueurs : même accueil front (équipe, pronos). L’admin reste accessible via le menu.
        return new RedirectResponse($this->urlGenerator->generate('app_homepage'));
    }

    /**
     * Refuse les redirections post-login vers les endpoints JSON (/api/…) ou hors du même hôte.
     */
    private function isSafeRedirectAfterLogin(Request $request, string $targetPath): bool
    {
        $targetPath = trim($targetPath);
        if ('' === $targetPath) {
            return false;
        }
        // URL « protocol-relative » (//autre-domaine/…) : à rejeter.
        if (str_starts_with($targetPath, '//')) {
            return false;
        }

        if (preg_match('#^https?://#i', $targetPath)) {
            $parsed = parse_url($targetPath);
            if (($parsed['host'] ?? '') !== $request->getHost()) {
                return false;
            }
            $path = $parsed['path'] ?? '/';
        } else {
            if (!str_starts_with($targetPath, '/')) {
                return false;
            }
            $path = parse_url($targetPath, PHP_URL_PATH) ?? $targetPath;
        }

        if (str_starts_with($path, '//')) {
            return false;
        }
        if (str_starts_with($path, '/api/') || '/api' === $path) {
            return false;
        }

        return true;
    }

    /**
     * Point d’entrée quand une ressource protégée est demandée sans session.
     * Pour les routes /api/, renvoyer du JSON 401 au lieu d’une redirection HTML vers /login :
     * sinon fetch suit la 302, reçoit une page 200 « Connexion », response.ok reste vrai et response.json() échoue.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(
                ['error' => 'Authentification requise.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return parent::start($request, $authException);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

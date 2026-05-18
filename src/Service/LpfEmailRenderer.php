<?php

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Rendu des e-mails transactionnels LPF'26 (layout commun).
 */
final class LpfEmailRenderer
{
    private const string LOGO_ASSET = 'images/lpf26-logo-gta.png';

    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getLogoUrl(): string
    {
        $context = $this->urlGenerator->getContext();
        $base = $context->getScheme().'://'.$context->getHost();
        $baseUrl = $context->getBaseUrl();
        if ('' !== $baseUrl) {
            $base .= $baseUrl;
        }

        return $base.'/'.implode('/', array_map(rawurlencode(...), explode('/', self::LOGO_ASSET)));
    }

    /**
     * @param array<string, mixed> $context Variables du contenu + optionnellement pageTitle, footerNote
     */
    public function render(string $contentTemplate, array $context = []): string
    {
        $content = $this->twig->render($contentTemplate, $context);

        return $this->twig->render('email/lpf_layout.html.twig', [
            'pageTitle' => $context['pageTitle'] ?? 'LPF\'26',
            'logoUrl' => $context['logoUrl'] ?? $this->getLogoUrl(),
            'footerNote' => $context['footerNote'] ?? 'Vous recevez ce message dans le cadre de LPF\'26 — Lotopotofoot.',
            'content' => $content,
        ]);
    }
}

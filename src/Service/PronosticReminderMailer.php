<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PronosticReminderMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LpfEmailRenderer $lpfEmailRenderer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function send(
        User $user,
        string $title,
        string $body,
        ?string $relativeUrl = '/matchs',
        string $ctaLabel = 'Voir les matchs et pronostiquer',
    ): void {
        $emailAddress = $user->getEmail();
        if (null === $emailAddress || '' === trim($emailAddress)) {
            throw new \InvalidArgumentException('Adresse e-mail du joueur manquante.');
        }

        $html = $this->lpfEmailRenderer->render('email/content/message.html.twig', [
            'pageTitle' => $title.' — LPF\'26',
            'title' => $title,
            'body' => $body,
            'actionUrl' => $this->resolveActionUrl($relativeUrl),
            'ctaLabel' => $ctaLabel,
            'greetingName' => $this->extractGreetingName($user),
        ]);

        $this->mailer->send(
            (new Email())
                ->to($emailAddress)
                ->subject($title.' — LPF\'26')
                ->html($html),
        );
    }

    private function extractGreetingName(User $user): ?string
    {
        $email = $user->getEmail();
        if (null === $email || !str_contains($email, '@')) {
            return null;
        }

        $local = explode('@', $email, 2)[0];
        $local = str_replace(['.', '_', '-'], ' ', $local);

        return ucfirst(trim($local));
    }

    private function resolveActionUrl(?string $relativeUrl): string
    {
        $relativeUrl = null !== $relativeUrl ? trim($relativeUrl) : '';
        if ('' === $relativeUrl) {
            return $this->urlGenerator->generate('app_matches', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if (str_starts_with($relativeUrl, 'http://') || str_starts_with($relativeUrl, 'https://')) {
            return $relativeUrl;
        }

        if (!str_starts_with($relativeUrl, '/')) {
            $relativeUrl = '/'.$relativeUrl;
        }

        if ('/matchs' === $relativeUrl) {
            return $this->urlGenerator->generate('app_matches', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $context = $this->urlGenerator->getContext();
        $base = $context->getScheme().'://'.$context->getHost();
        $baseUrl = $context->getBaseUrl();
        if ('' !== $baseUrl) {
            $base .= $baseUrl;
        }

        return $base.$relativeUrl;
    }
}

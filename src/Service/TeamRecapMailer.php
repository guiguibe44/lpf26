<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TeamRecapMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LpfEmailRenderer $lpfEmailRenderer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TeamRecapCopyProvider $copyProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $recapContext
     */
    public function send(User $user, string $nickname, array $recapContext): void
    {
        $email = trim((string) $user->getEmail());
        if ('' === $email) {
            return;
        }

        $teamId = $recapContext['team_id'] ?? null;
        $teamShowUrl = \is_int($teamId) || is_numeric($teamId)
            ? $this->urlGenerator->generate('app_team_show', ['id' => (int) $teamId], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('app_ranking', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $html = $this->lpfEmailRenderer->render('email/content/team_recap.html.twig', [
            'pageTitle' => $this->buildSubject($recapContext),
            'nickname' => $nickname,
            'recap' => $recapContext,
            'teamShowUrl' => $teamShowUrl,
            'rankingUrl' => $this->urlGenerator->generate('app_ranking', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'accountNotificationsUrl' => $this->urlGenerator->generate('app_account', [], UrlGeneratorInterface::ABSOLUTE_URL).'#notifications',
            'footerNote' => 'Récap d’équipe LPF\'26 — tous les 2 jours vers 9 h 30 (Paris).',
        ]);

        $this->mailer->send(
            (new Email())
                ->to($email)
                ->subject($this->buildSubject($recapContext))
                ->html($html),
        );
    }

    /**
     * @param array<string, mixed> $recap
     */
    public function buildSubject(array $recap): string
    {
        $teamName = (string) ($recap['team_name'] ?? 'Votre équipe');
        $points = (int) ($recap['total_team_points'] ?? 0);
        $laggardNickname = $recap['laggard']['nickname'] ?? '…';
        $period = (string) ($recap['period_label'] ?? '');

        $code = match (true) {
            $points >= 50 => 'subject.hot',
            $points > 0 => 'subject.positive',
            default => 'subject.neutral',
        };

        return $this->copyProvider->line($code, [
            'team_name' => $teamName,
            'total_points' => $points,
            'laggard_nickname' => (string) $laggardNickname,
            'period_label' => $period,
        ]);
    }
}

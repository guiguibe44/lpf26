<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\TeamMemberRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * E-mail envoyé au joueur lorsque sa cotisation est validée (admin).
 */
final class CotisationValidatedNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LpfEmailRenderer $lpfEmailRenderer,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(User $user): bool
    {
        $email = trim((string) $user->getEmail());
        if ('' === $email) {
            return false;
        }

        $teamName = null;
        $nickname = null;
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        if (null !== $member) {
            $nickname = $member->getNickname();
            $teamName = $member->getTeam()?->getName();
        }

        try {
            $html = $this->lpfEmailRenderer->render('email/content/cotisation_validated.html.twig', [
                'pageTitle' => 'Cotisation validée — LPF\'26',
                'userEmail' => $email,
                'teamName' => $teamName,
                'nickname' => $nickname,
                'footerNote' => 'Bonne compétition sur LPF\'26 — Lotopotofoot.',
            ]);

            $this->mailer->send(
                (new Email())
                    ->to($email)
                    ->subject('Cotisation validée — accès complet LPF\'26')
                    ->html($html),
            );

            $this->logger->info('E-mail cotisation validée envoyé.', ['user_email' => $email]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi e-mail cotisation validée.', [
                'user_email' => $email,
                'exception' => $e,
            ]);

            return false;
        }
    }
}

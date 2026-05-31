<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Security\SuperAdminAuthorization;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Alertes e-mail à l'organisateur (inscriptions, invitations).
 */
final class AdminActivityNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LpfEmailRenderer $lpfEmailRenderer,
        private readonly LoggerInterface $logger,
        private readonly string $adminNotificationEmail = SuperAdminAuthorization::EMAIL,
        private readonly string $adminRegistrationNotificationEmails = '',
    ) {
    }

    /**
     * Joueur 1 (inscription publique) ou joueur 2 (acceptation d'invitation).
     */
    public function notifyNewRegistration(
        User $user,
        Team $team,
        TeamMember $member,
        string $registrationKind,
        ?string $invitedPartnerEmail = null,
    ): void {
        $recipients = $this->registrationNotificationRecipients();
        if ([] === $recipients) {
            return;
        }

        try {
            $html = $this->lpfEmailRenderer->render('email/content/admin_new_registration.html.twig', [
                'pageTitle' => 'Nouvelle inscription — LPF\'26',
                'registrationKind' => $registrationKind,
                'userEmail' => (string) $user->getEmail(),
                'team' => $team,
                'member' => $member,
                'invitedPartnerEmail' => $invitedPartnerEmail,
                'footerNote' => 'Notification automatique LPF\'26.',
            ]);

            $this->mailer->send(
                (new Email())
                    ->to(...$recipients)
                    ->subject(sprintf('[LPF\'26] Nouvelle inscription — %s', (string) $team->getName()))
                    ->html($html),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Échec notification admin (inscription).', [
                'user_email' => $user->getEmail(),
                'exception' => $e,
            ]);
        }
    }

    public function notifyInvitationSent(TeamInvitation $invitation): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $team = $invitation->getTeam();
        if (!$team instanceof Team) {
            return;
        }

        try {
            $html = $this->lpfEmailRenderer->render('email/content/admin_invitation_sent.html.twig', [
                'pageTitle' => 'Invitation envoyée — LPF\'26',
                'team' => $team,
                'invitation' => $invitation,
                'inviter' => $invitation->getInvitedBy(),
                'footerNote' => 'Notification automatique LPF\'26.',
            ]);

            $this->mailer->send(
                (new Email())
                    ->to($this->adminNotificationEmail)
                    ->subject(sprintf(
                        '[LPF\'26] Invitation envoyée — %s → %s',
                        (string) $team->getName(),
                        (string) $invitation->getInvitedEmail(),
                    ))
                    ->html($html),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Échec notification admin (invitation).', [
                'invited_email' => $invitation->getInvitedEmail(),
                'exception' => $e,
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return '' !== trim($this->adminNotificationEmail);
    }

    /**
     * @return list<string>
     */
    private function registrationNotificationRecipients(): array
    {
        $fromRegistrationList = $this->parseEmailList($this->adminRegistrationNotificationEmails);
        if ([] !== $fromRegistrationList) {
            return $fromRegistrationList;
        }

        return $this->parseEmailList($this->adminNotificationEmail);
    }

    /**
     * @return list<string>
     */
    private function parseEmailList(string $raw): array
    {
        $unique = [];
        foreach (preg_split('/[,;]+/', $raw) ?: [] as $part) {
            $email = mb_strtolower(trim($part));
            if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $unique[$email] = $email;
        }

        return array_values($unique);
    }
}

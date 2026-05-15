<?php

namespace App\Service;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\User;
use App\Repository\TeamInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class TeamInvitationService
{
    public function __construct(
        private readonly TeamInvitationRepository $teamInvitationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
    ) {
    }

    /**
     * Crée ou renouvelle une invitation et envoie l'e-mail.
     *
     * @throws \InvalidArgumentException
     */
    public function sendInvitation(Team $team, User $invitedBy, string $invitedEmail): TeamInvitation
    {
        $invitedEmail = mb_strtolower(trim($invitedEmail));
        $inviterEmail = mb_strtolower((string) $invitedBy->getEmail());

        if ('' === $invitedEmail) {
            throw new \InvalidArgumentException('L\'adresse e-mail du partenaire est obligatoire.');
        }

        if ($invitedEmail === $inviterEmail) {
            throw new \InvalidArgumentException('L\'e-mail du partenaire doit être différent du vôtre.');
        }

        $invitation = $this->teamInvitationRepository->findPendingForTeamAndEmail($team, $invitedEmail);
        if (null === $invitation) {
            $invitation = (new TeamInvitation())
                ->setTeam($team)
                ->setInvitedBy($invitedBy)
                ->setInvitedEmail($invitedEmail);
            $this->entityManager->persist($invitation);
        } else {
            $invitation->setInvitedBy($invitedBy);
        }

        $invitation
            ->setToken(bin2hex(random_bytes(32)))
            ->setExpiresAt(new \DateTimeImmutable('+3 days'));

        $this->entityManager->flush();

        $html = $this->twig->render('registration/invitation_email.html.twig', [
            'team' => $team,
            'invitation' => $invitation,
        ]);

        $this->mailer->send(
            (new Email())
                ->to($invitedEmail)
                ->subject('Invitation à rejoindre votre équipe — LPF\'26')
                ->html($html),
        );

        return $invitation;
    }

    /**
     * Crée une équipe pour un utilisateur sans équipe et envoie l'invitation au partenaire.
     *
     * @return array{0: Team, 1: TeamInvitation}
     *
     * @throws \InvalidArgumentException
     */
    public function createTeamAndSendInvitation(User $invitedBy, string $teamName, string $invitedEmail): array
    {
        $teamName = trim($teamName);
        if ('' === $teamName) {
            throw new \InvalidArgumentException('Le nom de l\'équipe est obligatoire.');
        }

        $team = (new Team())->setName($teamName);
        $this->entityManager->persist($team);
        $this->entityManager->flush();

        $invitation = $this->sendInvitation($team, $invitedBy, $invitedEmail);

        return [$team, $invitation];
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Finalise l’acceptation d’une invitation (nouveau compte ou joueur déjà inscrit).
 */
final class TeamInvitationAcceptanceService
{
    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminActivityNotifier $adminActivityNotifier,
    ) {
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function resolveTeamForAcceptance(TeamInvitation $invitation): Team
    {
        $team = $invitation->getTeam();
        if (null === $team) {
            throw new \InvalidArgumentException('Invitation sans équipe associée.');
        }

        if ($this->teamMemberRepository->count(['team' => $team]) >= 2) {
            throw new \InvalidArgumentException('Cette équipe est déjà complète.');
        }

        return $team;
    }

    public function isAlreadyMemberOfInvitedTeam(User $user, TeamInvitation $invitation): bool
    {
        $team = $invitation->getTeam();
        if (null === $team) {
            return false;
        }

        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);

        return null !== $member && $member->getTeam()?->getId() === $team->getId();
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertUserCanJoinTeam(User $user, Team $team): void
    {
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        if (null === $member) {
            return;
        }

        if ($member->getTeam()?->getId() === $team->getId()) {
            return;
        }

        throw new \InvalidArgumentException('Ce compte est déjà rattaché à une autre équipe.');
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function assertNicknameAvailableInTeam(Team $team, string $nickname, ?int $excludeMemberId = null): void
    {
        $nickname = trim($nickname);
        if ('' === $nickname) {
            throw new \InvalidArgumentException('Le surnom est obligatoire.');
        }

        $existing = $this->teamMemberRepository->findOtherMemberWithNicknameInTeam($team, $nickname, $excludeMemberId);
        if (null !== $existing) {
            throw new \InvalidArgumentException('Ce surnom est déjà utilisé dans cette équipe.');
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function acceptForNewUser(TeamInvitation $invitation, string $nickname, string $plainPassword): User
    {
        if ($invitation->isAccepted()) {
            throw new \InvalidArgumentException('Cette invitation a déjà été acceptée.');
        }

        $team = $this->resolveTeamForAcceptance($invitation);
        $this->assertNicknameAvailableInTeam($team, $nickname);

        $email = mb_strtolower(trim((string) $invitation->getInvitedEmail()));
        if ('' === $email) {
            throw new \InvalidArgumentException('Invitation invalide (e-mail manquant).');
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser instanceof User) {
            throw new \InvalidArgumentException('Un compte existe déjà pour cet e-mail. Connectez-vous pour rejoindre l’équipe.');
        }

        $user = (new User())->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $member = $this->createMember($team, $user, $nickname);
        $invitation->markAsAccepted();

        $this->entityManager->persist($user);
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        $this->adminActivityNotifier->notifyNewRegistration($user, $team, $member, 'partner');

        return $user;
    }

    /**
     * Rattache un compte existant à l’équipe de l’invitation.
     *
     * @throws \InvalidArgumentException
     */
    public function acceptForExistingUser(TeamInvitation $invitation, User $user, string $nickname): TeamMember
    {
        $invitedEmail = mb_strtolower(trim((string) $invitation->getInvitedEmail()));
        $userEmail = mb_strtolower(trim((string) $user->getEmail()));

        if ('' === $invitedEmail || $userEmail !== $invitedEmail) {
            throw new \InvalidArgumentException('Ce compte ne correspond pas à l’e-mail invité.');
        }

        if ($this->isAlreadyMemberOfInvitedTeam($user, $invitation)) {
            if (!$invitation->isAccepted()) {
                $invitation->markAsAccepted();
                $this->entityManager->flush();
            }

            $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
            if ($member instanceof TeamMember) {
                return $member;
            }
        }

        if ($invitation->isAccepted()) {
            throw new \InvalidArgumentException('Cette invitation a déjà été acceptée.');
        }

        $team = $this->resolveTeamForAcceptance($invitation);
        $this->assertUserCanJoinTeam($user, $team);
        $this->assertNicknameAvailableInTeam($team, $nickname);

        $member = $this->createMember($team, $user, $nickname);
        $invitation->markAsAccepted();

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        $this->adminActivityNotifier->notifyNewRegistration($user, $team, $member, 'partner');

        return $member;
    }

    private function createMember(Team $team, User $user, string $nickname): TeamMember
    {
        $member = (new TeamMember())
            ->setPlayer($user)
            ->setNickname(trim($nickname));

        $team->addMember($member);

        return $member;
    }
}

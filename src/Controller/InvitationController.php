<?php

namespace App\Controller;

use App\Entity\TeamMember;
use App\Entity\User;
use App\Form\InvitationAcceptFormType;
use App\Service\AdminActivityNotifier;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class InvitationController extends AbstractController
{
    #[Route('/invitation/{token}', name: 'app_team_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        Request $request,
        TeamInvitationRepository $invitationRepository,
        TeamMemberRepository $teamMemberRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AdminActivityNotifier $adminActivityNotifier,
    ): Response {
        $invitation = $invitationRepository->findValidByToken($token);
        if (null === $invitation) {
            throw $this->createNotFoundException('Invitation invalide ou expiree.');
        }

        if ($teamMemberRepository->count(['team' => $invitation->getTeam()]) >= 2) {
            $this->addFlash('warning', 'Cette équipe est déjà complète.');

            return $this->redirectToRoute('app_login');
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy([
            'email' => $invitation->getInvitedEmail(),
        ]);
        if ($existingUser instanceof User) {
            $this->addFlash('info', 'Un compte existe deja pour cet email. Connectez-vous puis reouvrez le lien.');

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(InvitationAcceptFormType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $nickname = (string) $form->get('nickname')->getData();
            $plainPassword = (string) $form->get('plainPassword')->getData();

            if (null !== $teamMemberRepository->findOneBy([
                'team' => $invitation->getTeam(),
                'nickname' => $nickname,
            ])) {
                $this->addFlash('danger', 'Ce surnom est déjà utilisé dans cette équipe.');

                return $this->redirectToRoute('app_team_invitation_accept', ['token' => $token]);
            }

            $user = (new User())->setEmail((string) $invitation->getInvitedEmail());
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $member = (new TeamMember())
                ->setTeam($invitation->getTeam())
                ->setPlayer($user)
                ->setNickname($nickname);

            $invitation->markAsAccepted();

            $entityManager->persist($user);
            $entityManager->persist($member);
            $entityManager->flush();

            $team = $invitation->getTeam();
            if (null !== $team) {
                $adminActivityNotifier->notifyNewRegistration($user, $team, $member, 'partner');
            }

            $this->addFlash('success', 'Inscription terminée. Connectez-vous pour commencer.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/accept_invitation.html.twig', [
            'form' => $form,
            'invitation' => $invitation,
        ]);
    }
}

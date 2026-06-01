<?php

namespace App\Controller;

use App\Entity\TeamInvitation;
use App\Entity\User;
use App\Form\InvitationAcceptFormType;
use App\Repository\TeamInvitationRepository;
use App\Repository\UserRepository;
use App\Service\TeamInvitationAcceptanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InvitationController extends AbstractController
{
    #[Route('/invitation/{token}', name: 'app_team_invitation_accept', methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        Request $request,
        TeamInvitationRepository $invitationRepository,
        UserRepository $userRepository,
        TeamInvitationAcceptanceService $acceptanceService,
    ): Response {
        $invitation = $invitationRepository->findByToken($token);
        if (null === $invitation) {
            throw $this->createNotFoundException('Invitation invalide ou expirée.');
        }

        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->handleAuthenticatedUser($request, $invitation, $user, $acceptanceService);
        }

        if ($invitation->isExpired() && !$invitation->isAccepted()) {
            throw $this->createNotFoundException('Invitation invalide ou expirée.');
        }

        if ($invitation->isAccepted()) {
            $this->addFlash('info', 'Cette invitation a déjà été acceptée. Connectez-vous pour accéder à votre équipe.');

            return $this->redirectToRoute('app_login');
        }

        $existingUser = $userRepository->findOneBy([
            'email' => mb_strtolower(trim((string) $invitation->getInvitedEmail())),
        ]);
        if ($existingUser instanceof User) {
            $this->addFlash(
                'info',
                'Un compte existe déjà pour cet e-mail. Connectez-vous avec cette adresse, puis rouvrez le lien d’invitation pour rejoindre l’équipe.',
            );

            return $this->redirectToRoute('app_login', [
                '_target_path' => $request->getRequestUri(),
            ]);
        }

        try {
            $acceptanceService->resolveTeamForAcceptance($invitation);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(InvitationAcceptFormType::class, null, [
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $acceptanceService->acceptForNewUser(
                    $invitation,
                    (string) $form->get('nickname')->getData(),
                    (string) $form->get('plainPassword')->getData(),
                );
                $this->addFlash('success', 'Inscription terminée. Connectez-vous pour commencer.');

                return $this->redirectToRoute('app_login');
            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('registration/accept_invitation.html.twig', [
            'form' => $form,
            'invitation' => $invitation,
            'existing_account' => false,
        ]);
    }

    private function handleAuthenticatedUser(
        Request $request,
        TeamInvitation $invitation,
        User $user,
        TeamInvitationAcceptanceService $acceptanceService,
    ): Response {
        $invitedEmail = mb_strtolower(trim((string) $invitation->getInvitedEmail()));
        $userEmail = mb_strtolower(trim((string) $user->getEmail()));

        if ($userEmail !== $invitedEmail) {
            $this->addFlash(
                'warning',
                sprintf(
                    'Vous êtes connecté en tant que %s. Déconnectez-vous puis connectez-vous avec %s pour accepter cette invitation.',
                    $userEmail,
                    $invitedEmail,
                ),
            );

            return $this->redirectToRoute('app_account');
        }

        if ($acceptanceService->isAlreadyMemberOfInvitedTeam($user, $invitation)) {
            $this->addFlash('success', 'Vous êtes déjà membre de cette équipe.');

            return $this->redirectToRoute('app_account');
        }

        if ($invitation->isAccepted()) {
            $this->addFlash(
                'warning',
                'Cette invitation a déjà été utilisée mais votre compte n’est pas rattaché à l’équipe. Contactez l’organisateur.',
            );

            return $this->redirectToRoute('app_account');
        }

        if ($invitation->isExpired()) {
            $this->addFlash('danger', 'Cette invitation a expiré. Demandez une nouvelle invitation à votre partenaire.');

            return $this->redirectToRoute('app_account');
        }

        try {
            $acceptanceService->resolveTeamForAcceptance($invitation);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('warning', $e->getMessage());

            return $this->redirectToRoute('app_account');
        }

        $form = $this->createForm(InvitationAcceptFormType::class, null, [
            'require_password' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $acceptanceService->acceptForExistingUser(
                    $invitation,
                    $user,
                    (string) $form->get('nickname')->getData(),
                );
                $this->addFlash('success', 'Vous avez rejoint l’équipe. Votre compte est à jour.');

                return $this->redirectToRoute('app_account');
            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('registration/accept_invitation.html.twig', [
            'form' => $form,
            'invitation' => $invitation,
            'existing_account' => true,
        ]);
    }
}

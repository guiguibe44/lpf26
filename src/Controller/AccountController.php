<?php

namespace App\Controller;

use App\Entity\TeamMember;
use App\Entity\User;
use App\Form\AccountPasswordType;
use App\Form\AccountProfileType;
use App\Form\CreateTeamInvitationType;
use App\Form\ResendTeamInvitationType;
use App\Form\TeamManageType;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMemberRepository;
use App\Service\CompetitionStatus;
use App\Service\TeamInvitationService;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        TeamMemberRepository $teamMemberRepository,
        TeamInvitationRepository $teamInvitationRepository,
        TeamInvitationService $teamInvitationService,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        CompetitionStatus $competitionStatus,
        WebPushService $webPushService,
        FormFactoryInterface $formFactory,
        SluggerInterface $slugger,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $teamMember = $teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        if (null === $team) {
            $team = $teamInvitationRepository->findTeamForInviter($user);
        }

        $hasTeamMember = null !== $teamMember;
        $needsProfileSetup = null !== $team && !$hasTeamMember;
        $teamIsFull = null !== $team && $teamMemberRepository->count(['team' => $team]) >= 2;
        $canInviteTeammate = null !== $team && !$teamIsFull;

        $pendingInvitation = null !== $team
            ? $teamInvitationRepository->findPendingForTeam($team)
            : null;

        $passwordForm = $formFactory->createNamed('password_form', AccountPasswordType::class);
        $passwordForm->handleRequest($request);

        $profileForm = null;
        $teamForm = null;
        $invitationForm = null;
        $teamSetupForm = null;

        if ($hasTeamMember && null !== $teamMember && null !== $team) {
            $profileForm = $formFactory->createNamed('profile_form', AccountProfileType::class, $user);
            $profileForm->get('nickname')->setData($teamMember->getNickname());
            $profileForm->handleRequest($request);

            $teamForm = $formFactory->createNamed('team_form', TeamManageType::class, $team, [
                'lock_team_name' => $competitionStatus->isStarted(),
            ]);
            $teamForm->handleRequest($request);
        } elseif ($needsProfileSetup && null !== $team) {
            $profileForm = $formFactory->createNamed('profile_form', AccountProfileType::class, $user);
            $profileForm->handleRequest($request);
        }

        if (null === $team && !$hasTeamMember) {
            $teamSetupForm = $formFactory->createNamed('team_setup_form', CreateTeamInvitationType::class);
            $teamSetupForm->handleRequest($request);
        }

        if ($canInviteTeammate && null !== $team) {
            $invitationForm = $formFactory->createNamed('invitation_form', ResendTeamInvitationType::class);
            if (null !== $pendingInvitation) {
                $invitationForm->get('teammateEmail')->setData($pendingInvitation->getInvitedEmail());
            }
            $invitationForm->handleRequest($request);
        }

        if ($teamSetupForm?->isSubmitted() && $teamSetupForm->isValid()) {
            try {
                [$team, $invitation] = $teamInvitationService->createTeamAndSendInvitation(
                    $user,
                    (string) $teamSetupForm->get('teamName')->getData(),
                    (string) $teamSetupForm->get('teammateEmail')->getData(),
                );
                $needsProfileSetup = true;
                $canInviteTeammate = $teamMemberRepository->count(['team' => $team]) < 2;
                $pendingInvitation = $invitation;
                $this->addFlash('success', sprintf(
                    'Votre équipe « %s » a été créée. Une invitation a été envoyée à %s (valable jusqu\'au %s). Complétez ensuite votre profil joueur.',
                    (string) $team->getName(),
                    (string) $invitation->getInvitedEmail(),
                    $invitation->getExpiresAt()->format('d/m/Y H:i'),
                ));

                return $this->redirect($this->generateUrl('app_account').'#tab-compte');
            } catch (\InvalidArgumentException $e) {
                $teamSetupForm->get('teammateEmail')->addError(new FormError($e->getMessage()));
            }
        }

        if ($profileForm?->isSubmitted() && $profileForm->isValid() && null !== $team) {
            $nickname = (string) $profileForm->get('nickname')->getData();

            $existingNickname = $teamMemberRepository->findOneBy([
                'team' => $team,
                'nickname' => $nickname,
            ]);
            if (null !== $existingNickname && (!$hasTeamMember || $existingNickname->getId() !== $teamMember?->getId())) {
                $profileForm->get('nickname')->addError(new FormError('Ce surnom est deja utilise dans cette equipe.'));
            } else {
                $isNewProfile = !$hasTeamMember;
                $user->setEmail(mb_strtolower((string) $user->getEmail()));

                if (!$hasTeamMember) {
                    $teamMember = (new TeamMember())
                        ->setTeam($team)
                        ->setPlayer($user)
                        ->setNickname($nickname);
                    $entityManager->persist($teamMember);
                    $hasTeamMember = true;
                    $needsProfileSetup = false;
                } else {
                    $teamMember?->setNickname($nickname);
                }

                /** @var UploadedFile|null $avatarFile */
                $avatarFile = $profileForm->get('avatarFile')->getData();
                $removeAvatar = (bool) $profileForm->get('removeAvatar')->getData();
                $previousAvatar = $user->getAvatar();

                if ($avatarFile instanceof UploadedFile) {
                    $avatarPath = $this->uploadImageFile($avatarFile, 'avatars', $slugger);
                    $user->setAvatar($avatarPath);
                    $this->deletePublicFile($previousAvatar);
                } elseif ($removeAvatar) {
                    $user->setAvatar(null);
                    $this->deletePublicFile($previousAvatar);
                }

                $entityManager->flush();
                $this->addFlash('success', $isNewProfile
                    ? 'Votre profil joueur a ete cree.'
                    : 'Votre profil joueur a ete mis a jour.');

                return $this->redirect($this->generateUrl('app_account').'#tab-compte');
            }
        }

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $plainPassword = (string) $passwordForm->get('plainPassword')->getData();
            if ('' !== $plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $entityManager->flush();
                $this->addFlash('success', 'Votre mot de passe a ete modifie.');

                return $this->redirect($this->generateUrl('app_account').'#tab-compte');
            }

            $this->addFlash('warning', 'Aucun nouveau mot de passe saisi.');
        }

        if ($invitationForm?->isSubmitted() && $invitationForm->isValid() && null !== $team) {
            try {
                $invitation = $teamInvitationService->sendInvitation(
                    $team,
                    $user,
                    (string) $invitationForm->get('teammateEmail')->getData(),
                );
                $this->addFlash('success', sprintf(
                    'Invitation envoyée à %s. Votre partenaire doit ouvrir le lien reçu par e-mail avant le %s.',
                    (string) $invitation->getInvitedEmail(),
                    $invitation->getExpiresAt()->format('d/m/Y H:i'),
                ));

                return $this->redirect($this->generateUrl('app_account').'#tab-equipe');
            } catch (\InvalidArgumentException $e) {
                $invitationForm->get('teammateEmail')->addError(new FormError($e->getMessage()));
            }
        }

        if ($teamForm?->isSubmitted() && $teamForm->isValid() && null !== $team) {
            $nameLocked = $competitionStatus->isStarted();
            $originalTeamName = (string) $team->getName();

            if ($nameLocked && (string) $team->getName() !== $originalTeamName) {
                $team->setName($originalTeamName);
                $teamForm->get('name')->addError(new FormError('Le nom de l\'equipe est verrouille depuis le debut de la competition.'));
            } else {
                /** @var UploadedFile|null $logoFile */
                $logoFile = $teamForm->get('logoFile')->getData();
                $removeLogo = (bool) $teamForm->get('removeLogo')->getData();
                $previousLogo = $team->getLogo();

                if ($logoFile instanceof UploadedFile) {
                    $logoPath = $this->uploadImageFile($logoFile, 'team-logos', $slugger);
                    $team->setLogo($logoPath);
                    $this->deletePublicFile($previousLogo);
                } elseif ($removeLogo) {
                    $team->setLogo(null);
                    $this->deletePublicFile($previousLogo);
                }

                $entityManager->flush();
                $this->addFlash('success', 'Les informations de l\'equipe ont ete mises a jour.');

                return $this->redirect($this->generateUrl('app_account').'#tab-equipe');
            }
        }

        $dashboardPartners = [];
        $buteursPris = [];
        $teamMembers = [];
        if ($hasTeamMember) {
            $dashboardPartners = $teamMemberRepository->findPartnerUsers($user);
            $buteursPris = $teamMemberRepository->findButeursChoisisParAutresEquipes($user);
        }
        if (null !== $team) {
            $teamMembers = $teamMemberRepository->findBy(['team' => $team], ['joinedAt' => 'ASC']);
        }

        return $this->render('account/index.html.twig', [
            'profile_form' => $profileForm?->createView(),
            'password_form' => $passwordForm->createView(),
            'team_form' => $teamForm?->createView(),
            'invitation_form' => $invitationForm?->createView(),
            'team_setup_form' => $teamSetupForm?->createView(),
            'team' => $team,
            'has_team_member' => $hasTeamMember,
            'needs_profile_setup' => $needsProfileSetup,
            'can_invite_teammate' => $canInviteTeammate,
            'team_is_full' => $teamIsFull,
            'pending_invitation' => $pendingInvitation,
            'name_locked' => $competitionStatus->isStarted(),
            'competition_started' => $competitionStatus->isStarted(),
            'competition_start_at' => $competitionStatus->getStartAt(),
            'cotisation_payee' => $user->isCotisationPayee(),
            'dashboard_partners' => $dashboardPartners,
            'buteurs_pris_par_autres_equipes' => $buteursPris,
            'team_members' => $teamMembers,
            'push_vapid_configured' => $webPushService->isConfigured(),
        ]);
    }

    private function uploadImageFile(UploadedFile $file, string $subDir, SluggerInterface $slugger): string
    {
        $originalFilename = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $slugger->slug($originalFilename);
        $extension = $file->guessExtension() ?: 'bin';
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid('', true), $extension);

        $uploadRoot = $this->getParameter('kernel.project_dir').'/public/uploads/'.$subDir;
        if (!is_dir($uploadRoot)) {
            mkdir($uploadRoot, 0775, true);
        }

        $file->move($uploadRoot, $newFilename);

        return '/uploads/'.$subDir.'/'.$newFilename;
    }

    private function deletePublicFile(?string $publicPath): void
    {
        if (null === $publicPath || '' === $publicPath || !str_starts_with($publicPath, '/uploads/')) {
            return;
        }

        $absolutePath = $this->getParameter('kernel.project_dir').'/public'.$publicPath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

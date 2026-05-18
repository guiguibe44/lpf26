<?php

namespace App\Controller;

use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Form\AccountPasswordType;
use App\Form\AccountProfileType;
use App\Form\CreateTeamInvitationType;
use App\Form\ResendTeamInvitationType;
use App\Form\TeamFavoriteCountryType;
use App\Form\TeamManageType;
use App\Repository\ButRepository;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;
use App\Service\ButeurGoalScoringService;
use App\Service\CompetitionStatus;
use App\Enum\UploadImageCategory;
use App\Service\TeamFavoriteCountryService;
use App\Service\TeamInvitationService;
use App\Service\TeamJokerService;
use App\Service\UploadedImageStorage;
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
        TeamJokerService $teamJokerService,
        TeamFavoriteCountryService $teamFavoriteCountryService,
        UploadedImageStorage $uploadedImageStorage,
        ButRepository $butRepository,
        UserRepository $userRepository,
        ButeurGoalScoringService $buteurGoalScoringService,
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
        $favoriteCountryForm = null;
        $invitationForm = null;
        $teamSetupForm = null;
        $favoriteCountryState = null;

        if ($hasTeamMember && null !== $teamMember && null !== $team) {
            $profileForm = $formFactory->createNamed('profile_form', AccountProfileType::class, $user);
            $profileForm->get('nickname')->setData($teamMember->getNickname());
            $profileForm->handleRequest($request);

            $teamForm = $formFactory->createNamed('team_form', TeamManageType::class, $team, [
                'lock_team_name' => $competitionStatus->isStarted(),
            ]);
            $teamForm->handleRequest($request);

            $favoriteCountryState = $teamFavoriteCountryService->buildAccountState($team, $user);
            $favoriteCountryForm = $formFactory->createNamed(
                'favorite_country_form',
                TeamFavoriteCountryType::class,
                $team,
                ['disabled' => !$favoriteCountryState['can_manage']],
            );
            $favoriteCountryForm->handleRequest($request);
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
                $profileForm->get('nickname')->addError(new FormError('Ce surnom est déjà utilisé dans cette équipe.'));
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
                    $avatarPath = $uploadedImageStorage->storeUploadedFile($avatarFile, UploadImageCategory::Avatar);
                    $user->setAvatar($avatarPath);
                    $this->deletePublicFile($previousAvatar);
                } elseif ($removeAvatar) {
                    $user->setAvatar(null);
                    $this->deletePublicFile($previousAvatar);
                }

                $entityManager->flush();
                $this->addFlash('success', $isNewProfile
                    ? 'Votre profil joueur a été créé.'
                    : 'Votre profil joueur a été mis à jour.');

                return $this->redirect($this->generateUrl('app_account').'#tab-compte');
            }
        }

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $plainPassword = (string) $passwordForm->get('plainPassword')->getData();
            if ('' !== $plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $entityManager->flush();
                $this->addFlash('success', 'Votre mot de passe a été modifié.');

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

        if ($favoriteCountryForm?->isSubmitted() && $favoriteCountryForm->isValid() && null !== $team) {
            try {
                $teamFavoriteCountryService->assertCanManageFavoriteCountry($user);
                $entityManager->flush();
                $country = $team->getFavoriteCountry();
                if ($country instanceof Country) {
                    $this->addFlash('success', sprintf(
                        'Équipe favorite enregistrée : %s. Ce choix reste secret.',
                        (string) $country->getNom(),
                    ));
                } else {
                    $this->addFlash('success', 'Équipe favorite effacée.');
                }

                return $this->redirect($this->generateUrl('app_account').'#tab-equipe');
            } catch (\InvalidArgumentException $e) {
                $favoriteCountryForm->addError(new FormError($e->getMessage()));
            }
        }

        if ($teamForm?->isSubmitted() && $teamForm->isValid() && null !== $team) {
            $nameLocked = $competitionStatus->isStarted();
            $originalTeamName = (string) $team->getName();

            if ($nameLocked && (string) $team->getName() !== $originalTeamName) {
                $team->setName($originalTeamName);
                $teamForm->get('name')->addError(new FormError('Le nom de l\'équipe est verrouillé depuis le début de la compétition.'));
            } else {
                /** @var UploadedFile|null $logoFile */
                $logoFile = $teamForm->get('logoFile')->getData();
                $removeLogo = (bool) $teamForm->get('removeLogo')->getData();
                $previousLogo = $team->getLogo();

                if ($logoFile instanceof UploadedFile) {
                    $logoPath = $uploadedImageStorage->storeUploadedFile($logoFile, UploadImageCategory::TeamLogo);
                    $team->setLogo($logoPath);
                    $this->deletePublicFile($previousLogo);
                } elseif ($removeLogo) {
                    $team->setLogo(null);
                    $this->deletePublicFile($previousLogo);
                }

                $entityManager->flush();
                $this->addFlash('success', 'Les informations de l\'équipe ont été mises à jour.');

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

        $buteur_stats = null;
        $buteurChoisi = $user->getButeurChoisi();
        if ($buteurChoisi instanceof Buteur) {
            $buteurId = (int) $buteurChoisi->getId();
            $buteur_stats = [
                'goals' => $butRepository->countForButeur($buteurChoisi),
                'points' => $butRepository->sumPointsAttribuesForButeur($buteurChoisi),
                'cote' => $buteurGoalScoringService->getCurrentCoefficientForButeur($buteurChoisi),
                'selections' => $userRepository->countWithButeurChoisiId($buteurId),
                'total_players' => $userRepository->countWithButeurChoisi(),
            ];
        }

        return $this->render('account/index.html.twig', [
            'profile_form' => $profileForm?->createView(),
            'password_form' => $passwordForm->createView(),
            'team_form' => $teamForm?->createView(),
            'favorite_country_form' => $favoriteCountryForm?->createView(),
            'favorite_country_state' => $favoriteCountryState,
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
            'buteur_stats' => $buteur_stats,
            'team_members' => $teamMembers,
            'push_vapid_configured' => $webPushService->isConfigured(),
            'team_joker_overview' => null !== $team && $hasTeamMember
                ? $teamJokerService->buildOverviewForTeam($team)
                : [],
        ]);
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

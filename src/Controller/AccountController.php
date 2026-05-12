<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountPasswordType;
use App\Form\AccountProfileType;
use App\Form\TeamManageType;
use App\Repository\TeamMemberRepository;
use App\Service\CompetitionStatus;
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
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        CompetitionStatus $competitionStatus,
        FormFactoryInterface $formFactory,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $teamMember = $teamMemberRepository->findOneBy(['player' => $user]);
        if (null === $teamMember) {
            throw $this->createAccessDeniedException('Aucun profil joueur associe a ce compte.');
        }

        $team = $teamMember->getTeam();
        if (null === $team) {
            throw $this->createNotFoundException('Equipe introuvable.');
        }

        $nameLocked = $competitionStatus->isStarted();
        $originalTeamName = (string) $team->getName();

        $profileForm = $formFactory->createNamed('profile_form', AccountProfileType::class, $user);
        $profileForm->get('nickname')->setData($teamMember->getNickname());

        $passwordForm = $formFactory->createNamed('password_form', AccountPasswordType::class);
        $teamForm = $formFactory->createNamed('team_form', TeamManageType::class, $team, [
            'lock_team_name' => $nameLocked,
        ]);

        $profileForm->handleRequest($request);
        $passwordForm->handleRequest($request);
        $teamForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $nickname = (string) $profileForm->get('nickname')->getData();

            $existingNickname = $teamMemberRepository->findOneBy(['nickname' => $nickname]);
            if (null !== $existingNickname && $existingNickname->getId() !== $teamMember->getId()) {
                $profileForm->get('nickname')->addError(new FormError('Ce surnom est deja utilise.'));
            } else {
                $user->setEmail(mb_strtolower((string) $user->getEmail()));
                $teamMember->setNickname($nickname);

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
                $this->addFlash('success', 'Votre profil joueur a ete mis a jour.');

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

        if ($teamForm->isSubmitted() && $teamForm->isValid()) {
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

        return $this->render('account/index.html.twig', [
            'profile_form' => $profileForm->createView(),
            'password_form' => $passwordForm->createView(),
            'team_form' => $teamForm->createView(),
            'team' => $team,
            'name_locked' => $nameLocked,
            'competition_started' => $competitionStatus->isStarted(),
            'competition_start_at' => $competitionStatus->getStartAt(),
            'cotisation_payee' => $user->isCotisationPayee(),
            'dashboard_partners' => $teamMemberRepository->findPartnerUsers($user),
            'buteurs_pris_par_autres_equipes' => $teamMemberRepository->findButeursChoisisParAutresEquipes($user),
            'team_members' => $teamMemberRepository->findBy(['team' => $team], ['joinedAt' => 'ASC']),
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

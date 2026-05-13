<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $teamName = (string) $form->get('teamName')->getData();
            $nickname = (string) $form->get('nickname')->getData();
            $teammateEmail = mb_strtolower(trim((string) $form->get('teammateEmail')->getData()));

            $user->setEmail(mb_strtolower((string) $user->getEmail()));

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $team = (new Team())->setName($teamName);
            $owner = (new TeamMember())
                ->setTeam($team)
                ->setPlayer($user)
                ->setNickname($nickname);

            $entityManager->persist($team);
            $entityManager->persist($user);
            $entityManager->persist($owner);

            $invitation = null;
            if ('' !== $teammateEmail && $teammateEmail !== $user->getEmail()) {
                $invitation = (new TeamInvitation())
                    ->setTeam($team)
                    ->setInvitedBy($user)
                    ->setInvitedEmail($teammateEmail)
                    ->setToken(bin2hex(random_bytes(32)))
                    ->setExpiresAt(new \DateTimeImmutable('+3 days'));
                $entityManager->persist($invitation);
            }

            $entityManager->flush();

            if ($invitation instanceof TeamInvitation) {
                $invitationHtml = $this->renderView('registration/invitation_email.html.twig', [
                    'team' => $team,
                    'invitation' => $invitation,
                ]);

                $invitationEmail = (new Email())
                    ->to((string) $invitation->getInvitedEmail())
                    ->subject('Invitation a rejoindre votre equipe')
                    ->html($invitationHtml);
                $mailer->send($invitationEmail);
            }

            $ownerHtml = $this->renderView('registration/team_created_email.html.twig', [
                'team' => $team,
                'owner' => $owner,
                'invitedEmail' => $invitation?->getInvitedEmail(),
            ]);

            $ownerEmail = (new Email())
                ->to((string) $user->getEmail())
                ->subject('Votre equipe LPF 2026 est creee')
                ->html($ownerHtml);
            $mailer->send($ownerEmail);

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}

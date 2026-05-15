<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\LpfEmailRenderer;
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
        MailerInterface $mailer,
        LpfEmailRenderer $lpfEmailRenderer,
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
                $invitationHtml = $lpfEmailRenderer->render('email/content/invitation.html.twig', [
                    'pageTitle' => 'Invitation équipe — LPF\'26',
                    'team' => $team,
                    'invitation' => $invitation,
                    'footerNote' => 'Si vous n\'attendiez pas cette invitation, vous pouvez ignorer cet e-mail.',
                ]);

                $invitationEmail = (new Email())
                    ->to((string) $invitation->getInvitedEmail())
                    ->subject('Invitation à rejoindre votre équipe — LPF\'26')
                    ->html($invitationHtml);
                $mailer->send($invitationEmail);
            }

            $ownerHtml = $lpfEmailRenderer->render('email/content/team_created.html.twig', [
                'pageTitle' => 'Équipe créée — LPF\'26',
                'team' => $team,
                'owner' => $owner,
                'invitedEmail' => $invitation?->getInvitedEmail(),
                'footerNote' => 'Bienvenue sur LPF\'26 — Lotopotofoot.',
            ]);

            $ownerEmail = (new Email())
                ->to((string) $user->getEmail())
                ->subject('Votre équipe LPF\'26 est créée')
                ->html($ownerHtml);
            $mailer->send($ownerEmail);

            if ($invitation instanceof TeamInvitation) {
                $this->addFlash(
                    'success',
                    sprintf(
                        'Une invitation a été envoyée par e-mail à %s. Ton partenaire devra ouvrir ce message et suivre le lien pour terminer son inscription. Toi, tu peux dès maintenant te connecter avec l’e-mail et le mot de passe que tu as choisis.',
                        (string) $invitation->getInvitedEmail()
                    )
                );
            } else {
                $this->addFlash(
                    'success',
                    'Ton compte et ton équipe sont créés. Un e-mail de confirmation t’a été envoyé. Tu peux dès maintenant te connecter avec ton e-mail et ton mot de passe.'
                );
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}

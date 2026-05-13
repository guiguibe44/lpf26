<?php

namespace App\Controller\Admin;

use App\Entity\TeamInvitation;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class TeamInvitationCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamInvitation::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'invitedEmail',
            'token',
            'team.name',
            'invitedBy.email',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('team'),
            AssociationField::new('invitedBy'),
            TextField::new('invitedEmail'),
            DateTimeField::new('expiresAt'),
            DateTimeField::new('acceptedAt')->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $resend = Action::new('resendInvitation', 'Relancer l\'invitation')
            ->linkToCrudAction('resendInvitation')
            ->setCssClass('btn btn-warning')
            ->displayIf(static fn (TeamInvitation $invitation): bool => !$invitation->isAccepted());

        return $actions
            ->add(Crud::PAGE_INDEX, $resend)
            ->add(Crud::PAGE_DETAIL, $resend);
    }

    #[AdminRoute]
    public function resendInvitation(
        AdminContext $context,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $referrer = $context->getRequest()->headers->get('referer') ?? $this->generateUrl('admin_team_invitation_index');

        /** @var TeamInvitation|null $invitation */
        $invitation = $context->getEntity()->getInstance();
        if (!$invitation instanceof TeamInvitation) {
            $this->addFlash('danger', 'Invitation introuvable.');

            return $this->redirect($referrer);
        }

        if ($invitation->isAccepted()) {
            $this->addFlash('warning', 'Cette invitation est deja acceptee.');

            return $this->redirect($referrer);
        }

        if ($invitation->isExpired()) {
            $invitation->setExpiresAt(new \DateTimeImmutable('+3 days'));
            $entityManager->flush();
        }

        $invitationHtml = $this->renderView('registration/invitation_email.html.twig', [
            'team' => $invitation->getTeam(),
            'invitation' => $invitation,
        ]);

        $email = (new Email())
            ->from(new Address('no-reply@lpf2026.local', 'LPF 2026'))
            ->to((string) $invitation->getInvitedEmail())
            ->subject('Relance: invitation a rejoindre votre equipe')
            ->html($invitationHtml);
        $mailer->send($email);

        $this->addFlash('success', 'Invitation relancee avec succes.');

        return $this->redirect($referrer);
    }
}

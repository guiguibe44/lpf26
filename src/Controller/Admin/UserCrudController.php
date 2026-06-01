<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Service\CotisationPayeeValue;
use App\Service\CotisationValidatedNotifier;
use App\Service\UploadedImageFinalizeService;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

class UserCrudController extends AbstractAppCrudController
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly RequestStack $requestStack,
        private readonly CotisationValidatedNotifier $cotisationValidatedNotifier,
    ) {
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'email',
            'avatar',
            'buteurChoisi.prenom',
            'buteurChoisi.nom',
        ];
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $this->hydrateFromTeamMember($entityDto->getInstance());

        return parent::createEditFormBuilder($entityDto, $formOptions, $context);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('email'),
            TextField::new('adminListedTeamName', 'Équipe')
                ->hideOnForm()
                ->formatValue(function (mixed $value, ?User $user): string {
                    if (!$user instanceof User || null === $user->getId()) {
                        return '—';
                    }
                    $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
                    $team = $member?->getTeam();
                    if (null === $team) {
                        return '—';
                    }
                    $name = $team->getName();

                    return (null !== $name && '' !== $name) ? $name : ('#'.($team->getId() ?? '?'));
                }),
            TextField::new('nickname', 'Surnom')
                ->setHelp('Surnom affiché dans le jeu et le forum (enregistré sur la fiche membre d’équipe).')
                ->formatValue(function (?string $value, ?User $user): string {
                    if (null !== $value && '' !== $value) {
                        return $value;
                    }
                    if (!$user instanceof User || null === $user->getId()) {
                        return '—';
                    }
                    $nickname = $this->teamMemberRepository->findOneBy(['player' => $user])?->getNickname();

                    return null !== $nickname && '' !== $nickname ? $nickname : '—';
                }),
            Field::new('equipeRattachementAdmin', 'Équipe')
                ->onlyOnForms()
                ->setFormType(EntityType::class)
                ->setFormTypeOptions([
                    'class' => Team::class,
                    'choice_label' => 'name',
                    'required' => false,
                    'placeholder' => '— Aucune équipe —',
                    'query_builder' => static fn (TeamRepository $repository) => $repository->createQueryBuilder('t')
                        ->orderBy('t.name', 'ASC')
                        ->addOrderBy('t.id', 'ASC'),
                ])
                ->setHelp('Crée ou déplace la fiche « membre d’équipe ». Laisser vide pour retirer le joueur de son équipe (supprime la fiche membre). Si le surnom est déjà pris dans l’équipe cible, un suffixe est ajouté automatiquement.'),
            TextField::new('plainPassword', 'Mot de passe')
                ->setFormType(PasswordType::class)
                ->onlyOnForms()
                ->setRequired(Crud::PAGE_NEW === $pageName)
                ->hideOnIndex()
                ->setHelp(
                    Crud::PAGE_EDIT === $pageName
                        ? 'Laisser vide pour conserver le mot de passe actuel.'
                        : 'Minimum '.self::MIN_PASSWORD_LENGTH.' caractères.'
                ),
            BooleanField::new('grantAdmin', 'Administrateur')
                ->setHelp('Accès EasyAdmin en plus du jeu : équipe, pronostics et cotisation comme les autres joueurs.')
                ->hideOnIndex()
                ->onlyOnForms(),
            AssociationField::new('buteurChoisi')->setRequired(false),
            BooleanField::new('cotisationPayee')->setLabel('Cotisation payee'),
            ImageField::new('avatar')
                ->setLabel('Avatar')
                ->setBasePath('')
                ->formatValue(static fn (?string $value, ?User $user): ?string => UploadPathHelper::publicPath($user?->getAvatar(), 'avatars'))
                ->hideOnForm(),
            ImageField::new('avatarFilename')
                ->setLabel('Avatar')
                ->setBasePath('/uploads/avatars')
                ->setUploadDir('public/uploads/avatars')
                ->setRequired(false)
                ->onlyOnForms(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $notifyCotisationValidated = $entityInstance instanceof User && $entityInstance->isCotisationPayee();

        if ($entityInstance instanceof User) {
            $this->applyPasswordAndRoles($entityInstance, true);
            $this->applyOptimizedAvatar($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof User) {
            $this->syncTeamMemberFromAdminSelection($entityManager, $entityInstance);
            $this->syncNicknameToTeamMember($entityManager, $entityInstance);
            $entityManager->flush();

            if ($notifyCotisationValidated && $this->cotisationValidatedNotifier->notify($entityInstance)) {
                $this->addFlash('success', sprintf(
                    'Cotisation validée. Un e-mail de confirmation a été envoyé à %s.',
                    $entityInstance->getEmail(),
                ));
            }
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $notifyCotisationValidated = false;
        if ($entityInstance instanceof User && null !== $entityInstance->getId()) {
            $notifyCotisationValidated = $this->willCotisationBecomePaid($entityManager, $entityInstance);
            $this->applyPasswordAndRoles($entityInstance, false);
            $this->applyOptimizedAvatar($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof User) {
            $this->syncTeamMemberFromAdminSelection($entityManager, $entityInstance);
            $this->syncNicknameToTeamMember($entityManager, $entityInstance);
            $entityManager->flush();

            if ($notifyCotisationValidated) {
                if ($this->cotisationValidatedNotifier->notify($entityInstance)) {
                    $this->addFlash('success', sprintf(
                        'Cotisation validée. Un e-mail de confirmation a été envoyé à %s.',
                        $entityInstance->getEmail(),
                    ));
                } else {
                    $this->addFlash('warning', sprintf(
                        'Cotisation enregistrée, mais l’e-mail de confirmation n’a pas pu être envoyé à %s (voir les logs).',
                        $entityInstance->getEmail(),
                    ));
                }
            }
        }
    }

    /**
     * Détecte le passage à « cotisation payée » via le change set Doctrine (pas find() :
     * l’entité en mémoire porte déjà les valeurs du formulaire).
     */
    private function willCotisationBecomePaid(EntityManagerInterface $entityManager, User $user): bool
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        $metadata = $entityManager->getClassMetadata(User::class);
        $unitOfWork->computeChangeSet($metadata, $user);
        $changeSet = $unitOfWork->getEntityChangeSet($user);

        if (!isset($changeSet['cotisationPayee'])) {
            return false;
        }

        [$oldValue, $newValue] = $changeSet['cotisationPayee'];

        return CotisationPayeeValue::becamePaid($oldValue, $newValue);
    }

    private function hydrateFromTeamMember(object $entityInstance): void
    {
        if (!$entityInstance instanceof User || null === $entityInstance->getId()) {
            return;
        }

        $member = $this->teamMemberRepository->findOneBy(['player' => $entityInstance]);
        $entityInstance->setNickname($member?->getNickname());
        $entityInstance->setEquipeRattachementAdmin($member?->getTeam());
    }

    private function syncTeamMemberFromAdminSelection(EntityManagerInterface $entityManager, User $user): void
    {
        $selectedTeam = $user->getEquipeRattachementAdmin();
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);

        if (null === $selectedTeam) {
            if (null !== $member && $this->wasEquipeFieldExplicitlyCleared()) {
                $entityManager->remove($member);
            }

            return;
        }

        if (null === $member) {
            $nickname = $this->pickNicknameForNewTeamMember($user, $selectedTeam);
            $entityManager->persist(
                (new TeamMember())
                    ->setPlayer($user)
                    ->setTeam($selectedTeam)
                    ->setNickname($nickname),
            );

            return;
        }

        if ($member->getTeam()?->getId() === $selectedTeam->getId()) {
            return;
        }

        $member->setTeam($selectedTeam);
        $this->ensureNicknameUniqueOnTeamAfterMove($member, $selectedTeam);
        $entityManager->persist($member);
    }

    private function pickNicknameForNewTeamMember(User $user, Team $team): string
    {
        $raw = $user->getNickname();
        $base = (null !== $raw && '' !== trim($raw))
            ? mb_substr(trim($raw), 0, 50)
            : $this->deriveNicknameFromEmail($user);
        if (mb_strlen($base) < 3) {
            $base = $this->deriveNicknameFromEmail($user);
        }

        return $this->uniquifyNicknameInTeam($team, $base, null);
    }

    private function deriveNicknameFromEmail(User $user): string
    {
        $email = (string) $user->getEmail();
        $localPart = explode('@', $email, 2)[0] ?? 'joueur';
        $slug = preg_replace('/[^a-zA-Z0-9]/', '', $localPart) ?? '';
        if ('' === $slug || mb_strlen($slug) < 3) {
            $id = $user->getId();

            return mb_substr('joueur'.(null !== $id ? (string) $id : uniqid('', true)), 0, 50);
        }

        return mb_substr($slug, 0, 50);
    }

    private function uniquifyNicknameInTeam(Team $team, string $base, ?int $excludeMemberId): string
    {
        $candidate = mb_substr($base, 0, 50);
        for ($i = 0; $i < 100; ++$i) {
            if (null === $this->teamMemberRepository->findOtherMemberWithNicknameInTeam($team, $candidate, $excludeMemberId)) {
                return $candidate;
            }
            $suffix = '-'.($i + 1);
            $candidate = mb_substr($base, 0, max(3, 50 - mb_strlen($suffix))).$suffix;
        }

        throw new \RuntimeException('Impossible de générer un surnom unique dans cette équipe.');
    }

    private function ensureNicknameUniqueOnTeamAfterMove(TeamMember $member, Team $newTeam): void
    {
        $nick = $member->getNickname();
        if (null === $nick || '' === $nick) {
            $player = $member->getPlayer();
            if (!$player instanceof User) {
                throw new \RuntimeException('Joueur invalide.');
            }
            $member->setNickname($this->pickNicknameForNewTeamMember($player, $newTeam));

            return;
        }

        if (null !== $this->teamMemberRepository->findOtherMemberWithNicknameInTeam($newTeam, $nick, $member->getId())) {
            $member->setNickname($this->uniquifyNicknameInTeam($newTeam, $nick, $member->getId()));
        }
    }

    private function syncNicknameToTeamMember(EntityManagerInterface $entityManager, User $user): void
    {
        $nickname = $user->getNickname();
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        if (null === $member) {
            return;
        }

        if (null === $nickname || '' === $nickname) {
            return;
        }

        $team = $member->getTeam();
        if (null === $team) {
            return;
        }

        $trimmed = mb_substr(trim($nickname), 0, 50);
        if (null !== $this->teamMemberRepository->findOtherMemberWithNicknameInTeam($team, $trimmed, $member->getId())) {
            $trimmed = $this->uniquifyNicknameInTeam($team, $trimmed, $member->getId());
        }

        $member->setNickname($trimmed);
        $entityManager->persist($member);
    }

    private function applyPasswordAndRoles(User $user, bool $isNew): void
    {
        $plain = $user->getPlainPassword();
        if ($isNew) {
            if (null === $plain || '' === $plain) {
                throw new \RuntimeException('Le mot de passe est obligatoire pour un nouvel utilisateur.');
            }
            if (strlen($plain) < self::MIN_PASSWORD_LENGTH) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins '.self::MIN_PASSWORD_LENGTH.' caractères.');
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        } elseif (null !== $plain && '' !== $plain) {
            if (strlen($plain) < self::MIN_PASSWORD_LENGTH) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins '.self::MIN_PASSWORD_LENGTH.' caractères.');
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        }

        $user->setPlainPassword(null);
        $this->syncAdminRole($user);
    }

    private function syncAdminRole(User $user): void
    {
        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => 'ROLE_USER' !== $role,
        ));

        if ($user->isGrantAdmin()) {
            if (!\in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
            }
        } else {
            $roles = array_values(array_filter(
                $roles,
                static fn (string $role): bool => 'ROLE_ADMIN' !== $role,
            ));
        }

        $user->setRoles($roles);
    }

    /**
     * Retire l’équipe seulement si l’admin a choisi « Aucune équipe » dans le formulaire,
     * pas lorsque le champ virtuel est absent (ex. perte en session).
     */
    private function wasEquipeFieldExplicitlyCleared(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return false;
        }

        $payload = $this->extractSubmittedUserFormData($request);
        if (!\array_key_exists('equipeRattachementAdmin', $payload)) {
            return false;
        }

        $value = $payload['equipeRattachementAdmin'];

        return null === $value || '' === $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractSubmittedUserFormData(Request $request): array
    {
        foreach (['User', 'user', 'App\Entity\User'] as $key) {
            $block = $request->request->all($key);
            if (\is_array($block) && [] !== $block) {
                return $block;
            }
        }

        $ea = $request->request->all('ea');
        if (\is_array($ea)) {
            foreach (['new', 'edit'] as $mode) {
                $block = $ea[$mode]['User'] ?? $ea[$mode]['user'] ?? null;
                if (\is_array($block) && [] !== $block) {
                    return $block;
                }
            }
        }

        return [];
    }

    private function applyOptimizedAvatar(User $user): void
    {
        $avatar = $user->getAvatar();
        if (null === $avatar || '' === $avatar) {
            return;
        }

        $finalized = $this->uploadedImageFinalize->finalize(
            UploadPathHelper::normalizeStored($avatar, 'avatars') ?? basename($avatar),
            'avatars',
            asPublicPath: true,
        );

        if (null !== $finalized && '' !== $finalized) {
            $user->setAvatar($finalized);
        }
    }
}

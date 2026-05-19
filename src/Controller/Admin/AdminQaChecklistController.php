<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AdminQaChecklistNote;
use App\Entity\User;
use App\Repository\AdminQaChecklistNoteRepository;
use App\Service\AdminQaChecklistNotesBuilder;
use App\Service\AdminQaChecklistProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class AdminQaChecklistController extends AbstractController
{
    #[Route('/admin/checklist-qa', name: 'admin_qa_checklist', methods: ['GET'])]
    public function index(
        AdminQaChecklistProvider $checklistProvider,
        AdminQaChecklistNotesBuilder $notesBuilder,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $sections = $checklistProvider->getSections();
        $totalItems = 0;
        foreach ($sections as $section) {
            $totalItems += \count($section['items']);
        }

        return $this->render('admin/qa_checklist.html.twig', [
            'sections' => $sections,
            'total_items' => $totalItems,
            'notes_by_item' => $notesBuilder->buildNotesByItemId($user),
            'my_notes_by_item' => $notesBuilder->buildMyDraftByItemId($user),
            'valid_item_ids' => $checklistProvider->getAllItemIds(),
        ]);
    }

    #[Route('/admin/checklist-qa/note/{itemId}', name: 'admin_qa_checklist_note_save', methods: ['POST'])]
    public function saveNote(
        string $itemId,
        Request $request,
        AdminQaChecklistProvider $checklistProvider,
        AdminQaChecklistNoteRepository $noteRepository,
        AdminQaChecklistNotesBuilder $notesBuilder,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        if (!\in_array($itemId, $checklistProvider->getAllItemIds(), true)) {
            return $this->json(['error' => 'Point de checklist inconnu.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            $data = $request->request->all();
        }

        $csrfToken = (string) ($request->headers->get('X-CSRF-Token') ?? $data['_csrf_token'] ?? '');
        if (!$this->isCsrfTokenValid('admin_qa_note', $csrfToken)) {
            return $this->json(['error' => 'Jeton CSRF invalide. Rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $content = isset($data['content']) ? trim((string) $data['content']) : '';
        $userId = $user->getId();
        if (null === $userId) {
            return $this->json(['error' => 'Utilisateur invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $note = $noteRepository->findOneByItemAndUser($itemId, $userId);
        if (null === $note) {
            $note = (new AdminQaChecklistNote())
                ->setItemId($itemId)
                ->setAuthor($user);
            $entityManager->persist($note);
        }

        $note->setContent('' === $content ? null : $content);

        $errors = $validator->validate($note);
        if (\count($errors) > 0) {
            return $this->json([
                'error' => (string) $errors->get(0)->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (null === $note->getContent()) {
            if (null !== $note->getId()) {
                $entityManager->remove($note);
            }
            $entityManager->flush();

            return $this->json([
                'ok' => true,
                'removed' => true,
                'item_id' => $itemId,
            ]);
        }

        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'item_id' => $itemId,
            'note' => [
                'id' => (int) $note->getId(),
                'author_label' => $this->formatAuthorLabel($user),
                'author_id' => $userId,
                'is_mine' => true,
                'content' => $note->getContent(),
                'updated_at' => $note->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ],
            'all_notes' => $notesBuilder->buildNotesByItemId($user)[$itemId] ?? [],
        ]);
    }

    private function formatAuthorLabel(User $user): string
    {
        $nickname = $user->getNickname();
        if (null !== $nickname && '' !== trim($nickname)) {
            return trim($nickname);
        }

        $email = (string) $user->getEmail();
        if ('' !== $email && str_contains($email, '@')) {
            return (string) strstr($email, '@', true);
        }

        return $email !== '' ? $email : 'Administrateur';
    }
}

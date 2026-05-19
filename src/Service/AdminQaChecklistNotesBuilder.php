<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminQaChecklistNote;
use App\Entity\User;
use App\Repository\AdminQaChecklistNoteRepository;

final class AdminQaChecklistNotesBuilder
{
    public function __construct(
        private readonly AdminQaChecklistNoteRepository $noteRepository,
    ) {
    }

    /**
     * @return array<string, list<array{
     *     id: int,
     *     author_label: string,
     *     author_id: int|null,
     *     is_mine: bool,
     *     content: string,
     *     updated_at: \DateTimeImmutable,
     * }>>
     */
    public function buildNotesByItemId(User $currentUser): array
    {
        $byItem = [];
        $currentUserId = $currentUser->getId();

        foreach ($this->noteRepository->findAllWithAuthorOrdered() as $note) {
            $itemId = $note->getItemId();
            $content = $note->getContent();
            if (null === $content || '' === trim($content)) {
                continue;
            }

            $author = $note->getAuthor();
            $byItem[$itemId][] = [
                'id' => (int) $note->getId(),
                'author_label' => $this->formatAuthorLabel($author),
                'author_id' => $author?->getId(),
                'is_mine' => $author?->getId() === $currentUserId,
                'content' => $content,
                'updated_at' => $note->getUpdatedAt(),
            ];
        }

        return $byItem;
    }

    /**
     * @return array<string, string>
     */
    public function buildMyDraftByItemId(User $currentUser): array
    {
        $drafts = [];
        $userId = $currentUser->getId();
        if (null === $userId) {
            return $drafts;
        }

        foreach ($this->noteRepository->findByAuthor($userId) as $note) {
            $drafts[$note->getItemId()] = (string) ($note->getContent() ?? '');
        }

        return $drafts;
    }

    private function formatAuthorLabel(?User $author): string
    {
        if (null === $author) {
            return 'Administrateur';
        }

        $nickname = $author->getNickname();
        if (null !== $nickname && '' !== trim($nickname)) {
            return trim($nickname);
        }

        $email = (string) $author->getEmail();
        if ('' !== $email && str_contains($email, '@')) {
            return (string) strstr($email, '@', true);
        }

        return $email !== '' ? $email : 'Administrateur';
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ForumPost;
use App\Entity\User;
use App\Form\ForumPostType;
use App\Repository\ForumPostRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Données communes page forum complète et panneau latéral.
 */
final class ForumPageDataFactory
{
    public function __construct(
        private readonly ForumPostRepository $forumPostRepository,
        private readonly ForumAuthorResolver $authorResolver,
        private readonly ForumPostAccess $forumPostAccess,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array{
     *     posts: list<ForumPost>,
     *     author_map: array<int, array{nickname: string, avatar: string|null, initials: string}>,
     *     new_post_form: FormView,
     *     reply_forms: array<int, FormView>,
     *     edit_forms: array<int, FormView>,
     *     mention_suggestions_url: string,
     *     is_panel: bool
     * }
     */
    public function build(User $user, bool $isPanel): array
    {
        $posts = $this->forumPostRepository->findRecentRootPosts();
        $authorMap = $this->authorResolver->buildAuthorMap($posts);

        $newPost = (new ForumPost())->setAuthor($user);
        $newPostForm = $this->formFactory->create(ForumPostType::class, $newPost, [
            'action' => $this->urlGenerator->generate('app_forum'),
        ]);

        $replyForms = [];
        $editForms = [];
        foreach ($posts as $post) {
            $postId = $post->getId();
            if (null === $postId) {
                continue;
            }
            $reply = (new ForumPost())
                ->setAuthor($user)
                ->setParent($post);
            $replyForms[$postId] = $this->formFactory->create(ForumPostType::class, $reply, [
                'action' => $this->urlGenerator->generate('app_forum_reply', ['id' => $postId]),
            ])->createView();

            $this->collectEditForms($post, $user, $editForms);
        }

        return [
            'posts' => $posts,
            'author_map' => $authorMap,
            'new_post_form' => $newPostForm->createView(),
            'new_post_form_object' => $newPostForm,
            'reply_forms' => $replyForms,
            'edit_forms' => $editForms,
            'mention_suggestions_url' => $this->urlGenerator->generate('api_forum_mention_suggestions'),
            'is_panel' => $isPanel,
        ];
    }

    /**
     * @param array<int, FormView> $editForms
     */
    private function collectEditForms(ForumPost $post, User $user, array &$editForms): void
    {
        $postId = $post->getId();
        if (null !== $postId && $this->forumPostAccess->canModify($post, $user)) {
            $editForms[$postId] = $this->formFactory->create(ForumPostType::class, $post, [
                'action' => $this->urlGenerator->generate('app_forum_edit', ['id' => $postId]),
            ])->createView();
        }

        if ($post->isRoot()) {
            foreach ($post->getReplies() as $reply) {
                $this->collectEditForms($reply, $user, $editForms);
            }
        }
    }

    public function getNewPostForm(array $data): FormInterface
    {
        /** @var FormInterface $form */
        $form = $data['new_post_form_object'];

        return $form;
    }
}

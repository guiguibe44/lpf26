<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ForumPost;
use App\Entity\User;
use App\Form\ForumPostType;
use App\Service\ForumPageDataFactory;
use App\Service\ForumPostAccess;
use App\Service\ForumPostPublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
final class ForumController extends AbstractController
{
    public function __construct(
        private readonly ForumPageDataFactory $forumPageDataFactory,
        private readonly ForumPostPublisher $forumPostPublisher,
        private readonly ForumPostAccess $forumPostAccess,
    ) {
    }

    #[Route('/forum', name: 'app_forum', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();

        $data = $this->forumPageDataFactory->build($user, false);
        $form = $this->forumPageDataFactory->getNewPostForm($data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $raw = (string) $form->get('content')->getData();
                $this->forumPostPublisher->publish($form->getData(), $raw, $user);
                $this->addFlash('success', 'Message publié sur le forum.');

                return $this->redirectAfterForumAction($request, null);
            } catch (\InvalidArgumentException) {
                $form->get('content')->addError(new FormError('Le message ne peut pas être vide.'));
            }
        }

        unset($data['new_post_form_object']);

        return $this->render('forum/index.html.twig', $data);
    }

    #[Route('/forum/panel', name: 'app_forum_panel', methods: ['GET'])]
    public function panel(): Response
    {
        $data = $this->forumPageDataFactory->build($this->requireUser(), true);
        unset($data['new_post_form_object']);

        return $this->render('forum/_panel_frame.html.twig', $data);
    }

    #[Route('/forum/{id}/repondre', name: 'app_forum_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(Request $request, ForumPost $parent): Response
    {
        $user = $this->requireUser();

        if (!$parent->isRoot()) {
            throw $this->createNotFoundException();
        }

        $reply = (new ForumPost())
            ->setAuthor($user)
            ->setParent($parent);

        $replyForm = $this->createForm(ForumPostType::class, $reply, [
            'action' => $this->generateUrl('app_forum_reply', ['id' => $parent->getId()]),
        ]);
        $replyForm->handleRequest($request);

        if ($replyForm->isSubmitted() && $replyForm->isValid()) {
            try {
                $raw = (string) $replyForm->get('content')->getData();
                $this->forumPostPublisher->publish($reply, $raw, $user);
                $this->addFlash('success', 'Réponse publiée.');
            } catch (\InvalidArgumentException) {
                $this->addFlash('danger', 'La réponse ne peut pas être vide.');
            }
        } elseif ($replyForm->isSubmitted()) {
            $this->addFlash('danger', 'Impossible de publier la réponse. Vérifiez le contenu.');
        }

        return $this->redirectAfterForumAction($request, $parent);
    }

    #[Route('/forum/{id}/modifier', name: 'app_forum_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Request $request, ForumPost $post): Response
    {
        $user = $this->requireUser();

        if (!$this->forumPostAccess->canModify($post, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez plus modifier ce message.');
        }

        $form = $this->createForm(ForumPostType::class, $post, [
            'action' => $this->generateUrl('app_forum_edit', ['id' => $post->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $raw = (string) $form->get('content')->getData();
                $this->forumPostPublisher->update($post, $raw, $user);
                $this->addFlash('success', 'Message modifié.');
            } catch (\InvalidArgumentException) {
                $this->addFlash('danger', 'Le message ne peut pas être vide.');
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('danger', 'Impossible de modifier le message. Vérifiez le contenu.');
        }

        $scrollTarget = $post->isRoot() ? $post : $post->getParent();

        return $this->redirectAfterForumAction($request, $scrollTarget);
    }

    #[Route('/forum/{id}/supprimer', name: 'app_forum_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, ForumPost $post): Response
    {
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('forum_delete_'.$post->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        if (!$this->forumPostAccess->canModify($post, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez plus supprimer ce message.');
        }

        $scrollTarget = $post->isRoot() ? null : $post->getParent();
        $this->forumPostPublisher->delete($post);
        $this->addFlash('success', 'Message supprimé.');

        return $this->redirectAfterForumAction($request, $scrollTarget);
    }

    private function redirectAfterForumAction(Request $request, ?ForumPost $parent): Response
    {
        $fragment = null !== $parent && null !== $parent->getId()
            ? 'forum-post-'.$parent->getId()
            : null;

        if ($request->request->getBoolean('_forum_panel') || $request->query->getBoolean('panel')) {
            $referer = $request->headers->get('Referer') ?? $this->generateUrl('app_homepage');
            $referer = preg_replace('/#.*$/', '', $referer) ?? $referer;
            $hash = null !== $fragment ? '#forum-open-'.$fragment : '#forum-open';

            return $this->redirect($referer.$hash);
        }

        return $this->redirectToRoute('app_forum', [
            '_fragment' => $fragment,
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}

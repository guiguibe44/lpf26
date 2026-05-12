<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginFormAuthenticatorController extends AbstractController
{
    #[Route(path: '/', name: 'app_entrypoint')]
    public function entrypoint(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin' : 'app_homepage');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'admin' : 'app_homepage');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        $testUsers = [];

        if ('dev' === $this->getParameter('kernel.environment')) {
            $users = $entityManager->getRepository(User::class)->findBy([], ['id' => 'ASC']);
            foreach ($users as $user) {
                $email = (string) $user->getEmail();
                $testUsers[] = [
                    'email' => $email,
                    'roles' => implode(', ', $user->getRoles()),
                    'password_hint' => 'admin@lpf2026.local' === $email ? 'Admin2026!' : 'inconnu',
                ];
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'test_users' => $testUsers,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}

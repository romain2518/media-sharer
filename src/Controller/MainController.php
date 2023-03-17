<?php

namespace App\Controller;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_main_home')]
    public function home(UserInterface $user, JWTTokenManagerInterface $JWTManager): Response
    {
        return $this->render('main/home.html.twig', [
            'token' => $JWTManager->create($user),
        ]);
    }

    #[Route('/mentions-legales', name: 'app_main_legal-mentions')]
    public function legalMentions(): Response
    {
        return $this->render('main/legal_mentions.html.twig');
    }

    #[Route('/politique-de-confidentialite', name: 'app_main_privacy-policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('main/privacy_policy.html.twig');
    }

    #[Route('/cgu', name: 'app_main_terms')]
    public function terms(): Response
    {
        return $this->render('main/terms.html.twig');
    }
}

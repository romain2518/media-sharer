<?php

namespace App\Controller;

use App\Entity\UserReport;
use App\Form\UserReportType;
use App\Repository\UserReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class UserReportController extends AbstractController
{
    #[Route('/gestion/signalement/utilisateur/{limit}/{offset}', name: 'app_user-report_index', requirements: ['limit' => '\d+', 'offset' => '\d+'], methods: ['GET'])]
    public function index(UserReportRepository $userReportRepository, int $limit = 10, int $offset = 0): Response
    {
        return $this->render('user_report/index.html.twig', [
            'user_reports' => $userReportRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset),
            'previousOffset' => $offset - $limit < 0 ? 0 : $offset - $limit,
            'nextOffset' => $offset + $limit,
            'limit' => $limit,
        ]);
    }

    #[Route('/signalement/utilisateur', name: 'app_user-report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserReportRepository $userReportRepository, UserInterface $user): Response
    {
        $userReport = new UserReport();
        $form = $this->createForm(UserReportType::class, $userReport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userReport->setUser($user);

            $userReportRepository->save($userReport, true);

            return $this->redirectToRoute('app_main_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user_report/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(
        '/gestion/signalement/utilisateur/{id}/marquer-comme-{action}', name: 'app_user-report_mark-as', 
        requirements: ['action' => '^(traite)|(non-traite)|(important)|(non-important)$'], methods: ['POST'],
        defaults: ['_format' => 'json']
    )]
    public function markAs(Request $request, UserReport $userReport, string $action, UserReportRepository $userReportRepository)
    {
        $isValidToken = $this->isCsrfTokenValid('mark as'.$userReport->getId(), $request->request->get('_token'));
        if (!$isValidToken) {
            return $this->json('Jeton invalide', Response::HTTP_FORBIDDEN);
        }

        switch ($action) {
            case 'traite':
                $userReport->setIsProcessed(true);
                break;    
            case 'non-traite':
                $userReport->setIsProcessed(false);
                break;
            case 'important':
                $userReport->setIsImportant(true);
                break;
            case 'non-important':
                $userReport->setIsImportant(false);
                break;
        }
        $userReportRepository->save($userReport, true);

        return $this->json(
            $userReport,
            Response::HTTP_PARTIAL_CONTENT,
            [],
            [
                'groups' => [
                    'api_user-report'
                ]
            ]
        );
    }

    #[Route('/gestion/signalement/utilisateur/{id}/suppression', name: 'app_user-report_delete', methods: ['POST'])]
    public function delete(Request $request, UserReport $userReport, UserReportRepository $userReportRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$userReport->getId(), $request->request->get('_token'))) {
            $userReportRepository->remove($userReport, true);
        }

        return $this->redirectToRoute('app_user-report_index', [], Response::HTTP_SEE_OTHER);
    }
}

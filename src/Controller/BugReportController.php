<?php

namespace App\Controller;

use App\Entity\BugReport;
use App\Form\BugReportType;
use App\Repository\BugReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class BugReportController extends AbstractController
{
    #[Route('/gestion/signalement/bug/{limit}/{offset}', name: 'app_bug-report_index', requirements: ['limit' => '\d+', 'offset' => '\d+'], methods: ['GET'])]
    public function index(BugReportRepository $bugReportRepository, int $limit = 10, int $offset = 0): Response
    {
        return $this->render('bug_report/index.html.twig', [
            'bug_reports' => $bugReportRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset),
            'previousOffset' => $offset - $limit < 0 ? 0 : $offset - $limit,
            'nextOffset' => $offset + $limit,
            'limit' => $limit,
        ]);
    }

    #[Route('/signalement/bug', name: 'app_bug-report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, BugReportRepository $bugReportRepository, UserInterface $user): Response
    {
        $bugReport = new BugReport();
        $form = $this->createForm(BugReportType::class, $bugReport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bugReport->setUser($user);

            $bugReportRepository->save($bugReport, true);

            return $this->redirectToRoute('app_main_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('bug_report/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(
        '/gestion/signalement/bug/{id}/marquer-comme-{action}', name: 'app_bug-report_mark-as', 
        requirements: ['action' => '^(traite)|(non-traite)|(important)|(non-important)$'], methods: ['POST'],
        defaults: ['_format' => 'json']
    )]
    public function markAs(Request $request, BugReport $bugReport, string $action, BugReportRepository $bugReportRepository)
    {
        $isValidToken = $this->isCsrfTokenValid('mark as'.$bugReport->getId(), $request->request->get('_token'));
        if (!$isValidToken) {
            return $this->json('Jeton invalide', Response::HTTP_FORBIDDEN);
        }

        switch ($action) {
            case 'traite':
                $bugReport->setIsProcessed(true);
                break;    
            case 'non-traite':
                $bugReport->setIsProcessed(false);
                break;
            case 'important':
                $bugReport->setIsImportant(true);
                break;
            case 'non-important':
                $bugReport->setIsImportant(false);
                break;
        }
        $bugReportRepository->save($bugReport, true);

        return $this->json(
            $bugReport,
            Response::HTTP_PARTIAL_CONTENT,
            [],
            [
                'groups' => [
                    'api_bug-report'
                ]
            ]
        );
    }

    #[Route('/gestion/signalement/bug/{id}/suppression', name: 'app_bug-report_delete', methods: ['POST'])]
    public function delete(Request $request, BugReport $bugReport, BugReportRepository $bugReportRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$bugReport->getId(), $request->request->get('_token'))) {
            $bugReportRepository->remove($bugReport, true);
        }

        return $this->redirectToRoute('app_bug-report_index', [], Response::HTTP_SEE_OTHER);
    }
}

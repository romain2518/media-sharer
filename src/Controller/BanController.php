<?php

namespace App\Controller;

use App\Entity\Ban;
use App\Form\BanType;
use App\Repository\BanRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/gestion/ban/')]
class BanController extends AbstractController
{
    #[Route('{order}/{limit}/{offset}', name: 'app_ban_index', requirements: ['order' => '^(email)|(date)$', 'limit' => '\d+', 'offset' => '\d+'], methods: ['GET', 'POST'])]
    public function index(Request $request, BanRepository $banRepository, UserRepository $userRepository, UserInterface $user, string $order = 'date', int $limit = 20, int $offset = 0): Response
    {
        $ban = new Ban();
        $form = $this->createForm(BanType::class, $ban);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //? Checking if a user linked to this email does exist
            $targetedUser = $userRepository->findOneBy(['email' => $form->get('email')->getData()]);
            if (null !== $targetedUser) {
                $this->denyAccessUnlessGranted('USER_MANAGE', $targetedUser);
                $userRepository->remove($targetedUser);
            }

            $ban->setUser($user);
            $banRepository->save($ban, true);

            return $this->redirectToRoute('app_ban_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ban/index.html.twig', [
            'form' => $form,
            'bans' => $banRepository->findBy([], $order === 'email' ? ['email' => 'ASC'] : ['createdAt' => 'DESC'], $limit, $offset),
            'order' => $order,
            'previousOffset' => $offset - $limit < 0 ? 0 : $offset - $limit,
            'offset' => $offset,
            'nextOffset' => $offset + $limit,
            'limit' => $limit,
        ]);
    }

    #[Route('{id}/suppression', name: 'app_ban_delete', methods: ['POST'])]
    public function delete(Request $request, Ban $ban, BanRepository $banRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ban->getId(), $request->request->get('_token'))) {
            $banRepository->remove($ban, true);
        }

        return $this->redirectToRoute('app_ban_index', [], Response::HTTP_SEE_OTHER);
    }
}

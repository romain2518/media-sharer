<?php

namespace App\Controller;

use App\Entity\PatchNote;
use App\Form\PatchNoteType;
use App\Repository\PatchNoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class PatchNoteController extends AbstractController
{
    #[Route('/notes-de-maj/{limit}/{offset}', name: 'app_patch-note_index', requirements: ['limit' => '\d+', 'offset' => '\d+'], methods: ['GET'])]
    public function index(PatchNoteRepository $patchNoteRepository, int $limit = 10, int $offset = 0): Response
    {
        return $this->render('patch_note/index.html.twig', [
            'patch_notes' => $patchNoteRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset),
            'previousOffset' => $offset - $limit < 0 ? 0 : $offset - $limit,
            'nextOffset' => $offset + $limit,
            'limit' => $limit,
        ]);
    }

    #[Route('/gestion/note-de-maj/ajout', name: 'app_patch-note_new', methods: ['GET', 'POST'])]
    public function new(Request $request, PatchNoteRepository $patchNoteRepository, UserInterface $user): Response
    {
        $patchNote = new PatchNote();
        $form = $this->createForm(PatchNoteType::class, $patchNote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $patchNote->setUser($user);

            $patchNoteRepository->save($patchNote, true);

            return $this->redirectToRoute('app_patch-note_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('patch_note/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/gestion/note-de-maj/{id}/modification', name: 'app_patch-note_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PatchNote $patchNote, PatchNoteRepository $patchNoteRepository, UserInterface $user): Response
    {
        $form = $this->createForm(PatchNoteType::class, $patchNote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $patchNote->setUser($user);

            $patchNoteRepository->save($patchNote, true);

            return $this->redirectToRoute('app_patch-note_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('patch_note/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/gestion/note-de-maj/{id}/suppression', name: 'app_patch-note_delete', methods: ['POST'])]
    public function delete(Request $request, PatchNote $patchNote, PatchNoteRepository $patchNoteRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$patchNote->getId(), $request->request->get('_token'))) {
            $patchNoteRepository->remove($patchNote, true);
        }

        return $this->redirectToRoute('app_patch-note_index', [], Response::HTTP_SEE_OTHER);
    }
}

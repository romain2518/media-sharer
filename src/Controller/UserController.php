<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\DeleteAccountType;
use App\Form\EditLoginsType;
use App\Form\EditProfileType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class UserController extends AbstractController
{
    public function __construct(
        private EmailVerifier $emailVerifier,
    ) {
    }

    #[Route('/modification-des-identifiants', name: 'app_user_edit-logins', methods: ['GET', 'POST'])]
    public function editLogins(Request $request, UserInterface $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, Security $security): Response
    {
        /** @var User $user */

        $currentEmail = $user->getEmail();

        $form = $this->createForm(EditLoginsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainTextCurrentPassword = $form->get('currentPassword')->getData();
            if ($userPasswordHasher->isPasswordValid($user, $plainTextCurrentPassword)) {
                $plainTextNewPassword = $form->get('newPassword')->getData();
                if (null !== $plainTextNewPassword) {
                    $user->setPassword(
                        $userPasswordHasher->hashPassword(
                            $user,
                            $plainTextNewPassword
                        )
                    );
                }

                // If email changes, it needs to be confirmed
                if ($user->getEmail() !== $currentEmail) {
                    $user->setIsVerified(false);

                    $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                        (new TemplatedEmail())
                            ->from(new Address('no-reply@sengolas.com', 'No reply Mail Bot'))
                            ->to($user->getEmail())
                            ->subject('Confirmation de ton adresse mail')
                            ->htmlTemplate('registration/confirmation_email.html.twig')
                    );
                }

                $entityManager->flush();

                // If email changes, user is disconnected
                if ($user->getEmail() !== $currentEmail) {
                    $security->logout(false);
                }

                return $this->redirectToRoute('app_main_home', [], Response::HTTP_SEE_OTHER);
            }

            $this->addFlash('edit_logins_error', 'Mot de passe incorrect');
        }

        return $this->renderForm('user/edit_logins.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/modification-du-profil', name: 'app_user_edit-profile', methods: ['GET', 'POST'])]
    public function editProfile(Request $request, UserInterface $user, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */

        $form = $this->createForm(EditProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Setting file properties to null as user object is serialized and saved in the session (a File is not serializable)
            $user->setPictureFile(null);

            return $this->redirectToRoute('app_main_home', [], Response::HTTP_SEE_OTHER);
        }

        // Setting file properties to null as user object is serialized and saved in the session (a File is not serializable)
        $user->setPictureFile(null);
        
        return $this->render('user/edit_profile.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/suppression-du-compte', name: 'app_user_delete', methods: ['GET', 'POST'])]
    public function delte(Request $request, UserInterface $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, Security $security): Response
    {
        /** @var User $user */

        $form = $this->createForm(DeleteAccountType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainTextPassword = $form->get('password')->getData();
            if ($userPasswordHasher->isPasswordValid($user, $plainTextPassword)) {
                $entityManager->remove($user);
                $entityManager->flush();
                
                $security->logout(false);

                $this->addFlash('delete_success', 'Compte supprimé avec succès');

                return $this->redirectToRoute('app_main_home', [], Response::HTTP_SEE_OTHER);
            }
            
            $this->addFlash('delete_error', 'Mot de passe incorrect');
        }

        return $this->renderForm('user/delete.html.twig', [
            'form' => $form,
        ]);
    }
}

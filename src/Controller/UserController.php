<?php

namespace App\Controller;

use App\Entity\Ban;
use App\Entity\Conversation;
use App\Entity\User;
use App\Form\DeleteAccountType;
use App\Form\EditLoginsType;
use App\Form\EditProfileType;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Security\Exception\WebSocketInvalidRequestException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Vich\UploaderBundle\Handler\UploadHandler;

class UserController extends AbstractController
{
    #[Route(' /gestion/utilisateur/{limit}/{offset}', name: 'app_user_index', requirements: ['limit' => '\d+', 'offset' => '\d+'], methods: ['GET'])]
    public function index(UserRepository $userRepository, int $limit = 20, int $offset = 0): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset),
            'roles' => User::getTransRoles(),
            'availableRolesForEdit' => User::getAvailableRolesForEdit(),
            'previousOffset' => $offset - $limit < 0 ? 0 : $offset - $limit,
            'nextOffset' => $offset + $limit,
            'limit' => $limit,
        ]);
    }

    #[Route('/modification-des-identifiants', name: 'app_user_edit-logins', methods: ['GET', 'POST'])]
    public function editLogins(Request $request, UserInterface $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, Security $security, EmailVerifier $emailVerifier): Response
    {
        /** @var User $user */

        $currentEmail = $user->getEmail();
        $loginsError = false;

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

                    $emailVerifier->sendEmailConfirmation('app_verify_email', $user,
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
            $loginsError = true;
        }

        return $this->render(
            'user/edit_logins.html.twig', [
                'form' => $form,
            ],
            $loginsError ? new Response('', Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
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
    public function delete(Request $request, UserInterface $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, Security $security): Response
    {
        /** @var User $user */

        $loginsError = false;

        $form = $this->createForm(DeleteAccountType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainTextPassword = $form->get('password')->getData();
            if ($userPasswordHasher->isPasswordValid($user, $plainTextPassword)) {
                $entityManager->remove($user);
                $entityManager->flush();
                
                $security->logout(false);

                $this->addFlash('delete_success', 'Compte supprimé avec succès');

                return $this->redirectToRoute('app_register', [], Response::HTTP_SEE_OTHER);
            }
            
            $this->addFlash('delete_error', 'Mot de passe incorrect');
            $loginsError = true;
        }

        return $this->render(
            'user/delete.html.twig', [
                'form' => $form,
            ],
            $loginsError ? new Response('', Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    public static function block(string $action, User $targetedUser, UserInterface $user, EntityManagerInterface $entityManager, DateTimeFormatter $dateTimeFormatter): string
    {
        /** @var User $user */

        if (!in_array($action, ['block', 'unblock'])) {
            throw new WebSocketInvalidRequestException;
        }

        if ($targetedUser === $user) {
            throw new WebSocketInvalidRequestException(sprintf('Vous ne pouvez pas vous %s vous même.', $action === 'block' ? 'bloquer' : 'débloquer'));            
        }

        if ($action === 'block') {
            $user->addBlockedUser($targetedUser);
        } else {
            $user->removeBlockedUser($targetedUser);
        }

        $entityManager->flush();

        //? Normalizing data and returning JSON
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        $dateCallback = function ($innerObject) use ($dateTimeFormatter) {
            return ($innerObject instanceof \DateTime && null !== $innerObject) ? $dateTimeFormatter->formatDiff($innerObject, new \DateTime()) : '';
        };
        
        $defaultContext = [
            AbstractNormalizer::CALLBACKS => [
                'createdAt' => $dateCallback,
                'updatedAt' => $dateCallback,
            ],
        ];

        $normalizer = new ObjectNormalizer($classMetadataFactory, defaultContext: $defaultContext);
        $encoder = new JsonEncoder();
        $serializer = new Serializer([$normalizer], [$encoder]);

        if ($action === 'unblock') {
            /** @var ConversationRepository $conversationRepository */
            $conversationRepository = $entityManager->getRepository(Conversation::class);
            
            $conversation = $conversationRepository->findOneByUsersLight($user, $targetedUser);
            if (null !== $conversation) {
                return $serializer->serialize($conversation, 'json', ['groups' => 'api_conversation_list']);
            }            
        }

        return $serializer->serialize($targetedUser, 'json', ['groups' => 'api_user_light']);
    }

    #[Route(' /gestion/utilisateur/{id}/{action}', name: 'app_user_manage',
        requirements: ['action' => '^(reinitialisation-photo-de-profil)|(reinitialisation-pseudo)|(modification-role)|(ban)$'], 
        methods: ['POST'], defaults: ['_format' => 'json'])]
    public function manage(Request $request, User $user, string $action, EntityManagerInterface $entityManager, UserInterface $loggedUser, UploadHandler $vichUploader): JsonResponse
    {
        /** @var User $loggedUser */

        //? Checking CSRF Token
        $token = $request->request->get('_token');
        $isValidToken = $this->isCsrfTokenValid('manage '.$user->getId(), $token);
        if (!$isValidToken) {
            return $this->json('Jeton invalide', Response::HTTP_FORBIDDEN);
        }

        $this->denyAccessUnlessGranted('USER_MANAGE', $user);

        //? Checking if role does exist if action is to edit role
        if ('modification-role' === $action) {
            $newRole = $request->request->get('role');

            if (!in_array($newRole, User::getAvailableRolesForEdit())) {
                return $this->json('Ce rôle n\'existe pas.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        //? Edit the user
        switch ($action) {
            case 'reinitialisation-photo-de-profil':
                $vichUploader->remove($user, 'pictureFile');
                $user->setPicturePath(null);
                break;
            case 'reinitialisation-pseudo':
                $user->setPseudo('Membre #'. $user->getId());
                break;
            case 'modification-role':
                $user->setRoles([$newRole]);
                break;
            case 'ban':
                $ban = new Ban();
                $ban
                    ->setEmail($user->getEmail())
                    ->setComment(sprintf('Utilisateur banni : %s a été banni par %s.', $user->getPseudo(), $loggedUser->getPseudo()))
                    ->setUser($loggedUser)
                ;

                $entityManager->persist($ban);
                $entityManager->remove($user);
                break;
        }

        $entityManager->flush();

        if ('ban' === $action) {
            return $this->json(null, Response::HTTP_NO_CONTENT);
        }

        return $this->json(
            $user,
            Response::HTTP_PARTIAL_CONTENT,
            [],
            [
                'groups' => [
                    'api_user_detailed'
                ]
            ]
        );
    }
}

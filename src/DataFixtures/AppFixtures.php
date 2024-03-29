<?php

namespace App\DataFixtures;

use App\Entity\Ban;
use App\Entity\BugReport;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\PatchNote;
use App\Entity\Status;
use App\Entity\User;
use App\Entity\UserReport;
use DateInterval;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        //! User
        //? Superadmin
        $user = new User();
        $user
            ->setPseudo('Superadmin')
            ->setEmail($faker->unique()->freeEmail())
            ->setRoles(['ROLE_SUPERADMIN'])
            ->setPassword($this->hasher->hashPassword($user, "J'ai 19 ans."))
            ->setPicturePath('1.jfif')
            ->setIsVerified(1)
            ->setCreatedAt((new DateTime('now'))->add(new DateInterval('PT1S')))
            ;
        $users[] = $user;
        $manager->persist($user);

        //? Admin
        $user = new User();
        $user
            ->setPseudo('Admin')
            ->setEmail('used@mail.com')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword($this->hasher->hashPassword($user, "J'ai 19 ans."))
            ->setPicturePath('2.jfif')
            ->setIsVerified(1)
            ->setCreatedAt((new DateTime('now'))->add(new DateInterval('PT2S')))
            ;
        $users[] = $user;
        $manager->persist($user);

        //? 18 users
        for ($i=3; $i <= 20; $i++) { 
            $user = new User();
            $user
                ->setPseudo($faker->userName())
                ->setEmail($faker->unique()->freeEmail())
                ->setRoles(['ROLE_USER'])
                ->setPassword($this->hasher->hashPassword($user, "J'ai 19 ans."))
                ->setPicturePath("$i.jfif")
                ->setIsVerified(random_int(0, 1))
                ->setCreatedAt((new DateTime('now'))->add(new DateInterval('PT'.$i.'S')))
                ;
            $users[] = $user;
            $manager->persist($user);
        }

        //! Blocked user
        foreach ($users as $user) {
            foreach (array_rand($users, random_int(2, 3)) as $index) {
                // Prevent self blocking
                if ($users[$index] !== $user) {
                    $user->addBlockedUser($users[$index]);
                }
            }
        }

        //! Ban
        $ban = new Ban();
        $ban
            ->setUser($users[random_int(0,1)])
            ->setEmail('banned@mail.com')
            ->setComment($faker->realText(255))
            ;
        $manager->persist($ban);

        for ($i=1; $i < 20; $i++) { 
            $ban = new Ban();
            $ban
                ->setUser($users[random_int(0,1)])
                ->setEmail($faker->unique()->freeEmail())
                ->setComment(random_int(0, 1) ? $faker->realText(255) : null) // Randomly set a message or nothing
                ;
            $manager->persist($ban);
        }
        
        //! Bug report
        for ($i=0; $i < 5; $i++) { 
            $bugReport = new BugReport();
            $bugReport
                ->setUser($users[array_rand($users)])
                ->setUrl($faker->url())
                ->setComment($faker->realText(255))
                ->setIsImportant(random_int(0, 1))
                ->setIsProcessed(random_int(0, 1))
                ;
            $manager->persist($bugReport);
        }

        //! User report
        for ($i=0; $i < 5; $i++) { 
            $userReport = new UserReport();
            $userReport
                ->setUser($users[array_rand($users)])
                ->setReportedUser($users[array_rand($users)])
                ->setComment($faker->realText(255))
                ->setIsImportant(random_int(0, 1))
                ->setIsProcessed(random_int(0, 1))
                ;
            $manager->persist($userReport);
        }

        //! Patch note
        for ($i=0; $i < 5; $i++) { 
            $note = new PatchNote();
            $note
                ->setUser($users[random_int(0, 1)])
                ->setTitle($faker->realText(30))
                ->setNote($faker->realText(2000))
                ;
            $manager->persist($note);
        }

        //! Conversation
        $existingConversations = [];
        foreach ($users as $user) {
            for ($i=0; $i < random_int(1, 5); $i++) {
                // Finding second user
                do {
                    $user2 = $users[array_rand($users)];
                } while (
                    in_array($user->getEmail() . '-' . $user2->getEmail(), $existingConversations)
                    || in_array($user2->getEmail() . '-' . $user->getEmail(), $existingConversations)
                    || $user === $user2
                );
                
                $existingConversations[] = $user->getEmail() . '-' . $user2->getEmail();

                /// Creating conversation
                $conversation = new Conversation();
                $conversation
                    ->addUser($user)
                    ->addUser($user2)
                    ;
                $manager->persist($conversation);

                // Adding statuses
                $status1 = new Status();
                $status1
                    ->setIsRead(random_int(0, 1))
                    ->setUser($user)
                    ->setConversation($conversation)
                    ;
                $manager->persist($status1);
                
                $status2 = new Status();
                $status2
                    ->setIsRead(random_int(0, 1))
                    ->setUser($user2)
                    ->setConversation($conversation)
                    ;
                $manager->persist($status2);

                $conversation
                    ->addStatus($status1)
                    ->addStatus($status2)
                    ;
                
                // Adding messages
                for ($j=0; $j < random_int(3, 100); $j++) {
                    $message = new Message();
                    $message
                        ->setMessage($faker->realText(255))
                        ->setUser(random_int(0, 1) ? $user : $user2)
                        ->setConversation($conversation)
                        ;
                    $manager->persist($message);
                }
            }
        }

        $manager->flush();
    }
}

<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 *
 * @method Conversation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Conversation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Conversation[]    findAll()
 * @method Conversation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function save(Conversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Conversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a conversation between two users (load conversation, statuses & users)
     * 
     * @param User $user1
     * @param User $user2
     * @return Conversation|null Returns Conversation object or null
     */
    public function findOneByUsersLight(User $user1, User $user2): Conversation|null
    {
        return $this->createQueryBuilder('c')
            ->addSelect('s, u')
            ->innerJoin('c.statuses', 's')
            ->innerJoin('s.user', 'su')
            ->innerJoin('c.users', 'u')
            ->andWhere(':user1 MEMBER OF c.users')
            ->andWhere(':user2 MEMBER OF c.users')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->addOrderBy('su.id', 'ASC')
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find a conversation between two users (load conversation, statuses, users AND messages)
     * 
     * @param User $user1
     * @param User $user2
     * @return Conversation|null Returns Conversation object or null
     */
    public function findOneByUsersDetailed(User $user1, User $user2): Conversation|null
    {
        return $this->createQueryBuilder('c')
            ->addSelect('s, u, m')
            ->innerJoin('c.statuses', 's')
            ->innerJoin('s.user', 'su')
            ->innerJoin('c.users', 'u')
            ->leftJoin('c.messages', 'm')
            ->leftJoin('m.user', 'mu')
            ->andWhere(':user1 MEMBER OF c.users')
            ->andWhere(':user2 MEMBER OF c.users')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->addOrderBy('su.id', 'ASC')
            ->addOrderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Find all conversations that a user have (load conversation, statuses & users)
     * 
     * @param User $user
     * @return Conversation[] Returns an array of Conversation objects
     */
    public function findAllByUser(User $user): array
    {
        $blockedUsersIdSubQuery = $this->_em->createQueryBuilder()
            ->select('bu.id')
            ->from('App\Entity\User', 'u2')
            ->leftJoin('u2.blockedUsers', 'bu')
            ->where('u2 = :user')
            ->getDQL();

        $conversationsWithBlockedUserSubQuery = $this->createQueryBuilder('c2')
            ->innerJoin('c2.users', 'u3')
            ->where('u3.id IN (' . $blockedUsersIdSubQuery . ')')
            ->getDQL();

        $conversationsWithMoreThanZeroMessageSubQuery = $this->createQueryBuilder('c3')
            // Doing an inner join on messages is enough to select only conversations with more than 0 messages
            // since conversation with 0 messages won't be joined
            ->innerJoin('c3.messages', 'm')
            ->innerJoin('c3.users', 'u4')
            ->where(':user MEMBER OF c3.users')
            ->getDQL();

        return $this->createQueryBuilder('c')
            ->addSelect('s, u')
            ->innerJoin('c.statuses', 's')
            ->innerJoin('s.user', 'su')
            ->innerJoin('c.users', 'u')
            ->andWhere(':user MEMBER OF c.users')
            ->andWhere('c NOT IN (' . $conversationsWithBlockedUserSubQuery . ')')
            ->andWhere('c IN (' . $conversationsWithMoreThanZeroMessageSubQuery . ')')
            ->setParameter('user', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->addOrderBy('su.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Conversation[] Returns an array of Conversation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Conversation
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

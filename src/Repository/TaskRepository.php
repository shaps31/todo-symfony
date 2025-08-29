<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    //    /**
    //     * @return Task[] Returns an array of Task objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Task
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function searchFor(User $owner, array $filters, int $page=1, int $limit=10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner')->setParameter('owner', $owner)
            ->orderBy('t.dueAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :s')->setParameter('s', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $qb->andWhere('t.title LIKE :q OR t.description LIKE :q')
                ->setParameter('q', '%'.$filters['q'].'%');
        }

        return $qb->setFirstResult(($page-1)*$limit)
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countFor(User $owner, array $filters): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.owner = :owner')->setParameter('owner', $owner);

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :s')->setParameter('s', $filters['status']);
        }
        if (!empty($filters['q'])) {
            $qb->andWhere('t.title LIKE :q OR t.description LIKE :q')
                ->setParameter('q', '%'.$filters['q'].'%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

}

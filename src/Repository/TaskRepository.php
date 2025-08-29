<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    /** Statuts autorisés pour les filtres */
    private const ALLOWED_STATUSES = ['todo', 'doing', 'done'];

    /** Champs autorisés pour le tri */
    private const ALLOWED_SORTS = ['dueAt', 'createdAt'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Liste paginée des tâches d'un owner avec filtres facultatifs.
     *
     * $filters:
     *   - q: string (recherche titre/description)
     *   - status: todo|doing|done
     *   - overdue: bool (true => seulement en retard)
     *   - sort: dueAt|createdAt (default: dueAt)
     *   - dir: ASC|DESC (default: DESC)
     */
    public function searchFor(User $owner, array $filters, int $page = 1, int $limit = 10): array
    {
        $page  = max(1, $page);
        $limit = max(1, min(100, $limit));

        $qb = $this->createBaseQB($owner, $filters)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /** Compte total des tâches correspondant aux mêmes filtres (pour la pagination). */
    public function countFor(User $owner, array $filters): int
    {
        $qb = $this->createBaseQB($owner, $filters)
            ->select('COUNT(t.id)')
            ->resetDQLPart('orderBy'); // inutile pour un COUNT

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** Construit le QueryBuilder commun (owner + filtres). */
    private function createBaseQB(User $owner, array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.owner = :owner')->setParameter('owner', $owner);

        // Filtre status (uniquement valeurs permises)
        if (!empty($filters['status']) && in_array($filters['status'], self::ALLOWED_STATUSES, true)) {
            $qb->andWhere('t.status = :s')->setParameter('s', $filters['status']);
        }

        // Recherche texte (insensible à la casse)
        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            if ($q !== '') {
                $qb->andWhere('LOWER(t.title) LIKE :q OR LOWER(t.description) LIKE :q')
                    ->setParameter('q', '%'.mb_strtolower($q).'%');
            }
        }

        // En retard: dueAt passé et statut != done
        if (!empty($filters['overdue'])) {
            $qb->andWhere('t.dueAt IS NOT NULL')
                ->andWhere('t.dueAt < :now')
                ->andWhere('t.status <> :done')
                ->setParameter('now', new \DateTimeImmutable())
                ->setParameter('done', 'done');
        }

        // Tri (sécurisé)
        $sort = $filters['sort'] ?? 'dueAt';
        $dir  = strtoupper($filters['dir'] ?? 'DESC');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'dueAt';
        }
        $dir = $dir === 'ASC' ? 'ASC' : 'DESC';
        $qb->addOrderBy('t.'.$sort, $dir);

        return $qb;
    }
}

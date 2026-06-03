<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * A customer's activities, newest first. Optionally limited to those
     * tied to a given opportunity.
     *
     * @return Activity[]
     */
    public function findForCustomer(Customer $customer, ?int $opportunityId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('a.occurredAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (null !== $opportunityId) {
            $qb->andWhere('a.opportunity = :opportunity')
                ->setParameter('opportunity', $opportunityId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * The cross-customer activity feed (every type, skipping soft-deleted
     * customers), ordered by occurredAt ascending so open tasks surface by
     * urgency. Open and closed are both returned; the dashboard filters by
     * status client-side. Optionally limited to a given creator.
     *
     * @return Activity[]
     */
    public function findFeed(?User $createdBy = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->innerJoin('a.customer', 'c')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('a.occurredAt', 'ASC')
            ->addOrderBy('a.id', 'ASC');

        if (null !== $createdBy) {
            $qb->andWhere('a.createdBy = :user')->setParameter('user', $createdBy);
        }

        return $qb->getQuery()->getResult();
    }
}

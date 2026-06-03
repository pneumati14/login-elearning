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
     * The cross-customer activity feed (every type), ordered by occurredAt
     * ascending so open tasks surface by urgency. Open and closed are both
     * returned; the dashboard filters by status client-side. Standalone
     * (customer-less) tasks are included; customer-linked ones are skipped
     * when their customer is soft-deleted. Optionally limited to one
     * assignee (the "my tasks" scope = tasks I'm responsible for).
     *
     * @return Activity[]
     */
    public function findFeed(?User $assignee = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.customer', 'c')
            ->andWhere('a.customer IS NULL OR c.deletedAt IS NULL')
            ->orderBy('a.occurredAt', 'ASC')
            ->addOrderBy('a.id', 'ASC');

        if (null !== $assignee) {
            $qb->andWhere('a.assignee = :assignee')->setParameter('assignee', $assignee);
        }

        return $qb->getQuery()->getResult();
    }
}

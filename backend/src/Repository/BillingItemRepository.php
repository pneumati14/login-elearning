<?php

namespace App\Repository;

use App\Entity\BillingItem;
use App\Entity\Opportunity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BillingItem>
 */
class BillingItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillingItem::class);
    }

    /**
     * Were billing items already snapshotted from this deal? Guards
     * against duplicates when a reopened deal is won a second time.
     */
    public function existsForOpportunity(Opportunity $opportunity): bool
    {
        return null !== $this->createQueryBuilder('b')
            ->select('b.id')
            ->andWhere('b.opportunity = :opportunity')
            ->setParameter('opportunity', $opportunity)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

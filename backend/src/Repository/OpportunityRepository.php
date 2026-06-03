<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Opportunity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Opportunity>
 */
class OpportunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Opportunity::class);
    }

    /**
     * Opportunities for one customer, newest first.
     *
     * @return Opportunity[]
     */
    public function findForCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

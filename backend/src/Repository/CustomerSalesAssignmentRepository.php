<?php

namespace App\Repository;

use App\Entity\CustomerSalesAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerSalesAssignment>
 */
class CustomerSalesAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSalesAssignment::class);
    }
}

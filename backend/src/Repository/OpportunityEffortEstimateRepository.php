<?php

namespace App\Repository;

use App\Entity\OpportunityEffortEstimate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OpportunityEffortEstimate>
 */
class OpportunityEffortEstimateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OpportunityEffortEstimate::class);
    }
}

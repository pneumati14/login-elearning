<?php

namespace App\Repository;

use App\Entity\OpportunityStageChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OpportunityStageChange>
 */
class OpportunityStageChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OpportunityStageChange::class);
    }
}

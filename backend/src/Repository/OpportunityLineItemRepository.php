<?php

namespace App\Repository;

use App\Entity\OpportunityLineItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OpportunityLineItem>
 */
class OpportunityLineItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OpportunityLineItem::class);
    }
}

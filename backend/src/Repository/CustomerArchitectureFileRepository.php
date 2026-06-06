<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerArchitectureFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerArchitectureFile>
 */
class CustomerArchitectureFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerArchitectureFile::class);
    }

    /**
     * @return CustomerArchitectureFile[]
     */
    public function findForCustomer(Customer $customer): array
    {
        return $this->findBy(['customer' => $customer], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }
}

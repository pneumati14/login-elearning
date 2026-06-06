<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerArchitecture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerArchitecture>
 */
class CustomerArchitectureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerArchitecture::class);
    }

    public function findForCustomer(Customer $customer): ?CustomerArchitecture
    {
        return $this->findOneBy(['customer' => $customer]);
    }
}

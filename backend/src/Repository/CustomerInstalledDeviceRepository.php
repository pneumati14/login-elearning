<?php

namespace App\Repository;

use App\Entity\CustomerInstalledDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerInstalledDevice>
 */
class CustomerInstalledDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerInstalledDevice::class);
    }
}

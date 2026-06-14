<?php

namespace App\Repository;

use App\Entity\NoteFolder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NoteFolder>
 */
class NoteFolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NoteFolder::class);
    }

    /**
     * All folders owned by the user, ordered for the tree (position then
     * name). The tree is assembled client-side from the parent links.
     *
     * @return NoteFolder[]
     */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php
namespace App\Repository;

use App\Entity\ChildGuardian;
use App\Entity\Guardian;
use App\Entity\Child;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChildGuardianRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChildGuardian::class);
    }

    public function findByGuardian(Guardian $guardian): array
    {
        return $this->createQueryBuilder('cg')
            ->join('cg.child', 'c')
            ->where('cg.guardian = :guardian')
            ->andWhere('cg.inviteAccepted = true')
            ->setParameter('guardian', $guardian)
            ->orderBy('c.firstName', 'ASC')
            ->getQuery()->getResult();
    }

    public function findByChild(Child $child): array
    {
        return $this->createQueryBuilder('cg')
            ->innerJoin('cg.guardian', 'g')
            ->addSelect('g')
            ->where('cg.child = :child')
            ->setParameter('child', $child)
            ->orderBy('cg.permission', 'DESC')
            ->getQuery()->getResult();
    }


    public function findOneByChildAndGuardian(Child $child, Guardian $guardian): ?ChildGuardian
    {
        return $this->findOneBy(['child' => $child, 'guardian' => $guardian]);
    }
}

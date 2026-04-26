<?php

namespace App\Repository;

use App\Entity\Child;
use App\Entity\EventImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventImage::class);
    }

    public function findByChild(Child $child): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.child = :child')
            ->setParameter('child', $child)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

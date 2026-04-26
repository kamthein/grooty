<?php
namespace App\Repository;

use App\Entity\Guardian;
use App\Entity\Note;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    public function findForChildAndGuardian(\App\Entity\Child $child, Guardian $guardian): array
    {
        $notes = $this->createQueryBuilder('n')
            ->leftJoin('n.attachments', 'a')
            ->addSelect('a')
            ->where('n.child = :child')
            ->setParameter('child', $child)
            ->orderBy('n.createdAt', 'ASC')
            ->getQuery()->getResult();

        return array_values(array_filter($notes, fn(Note $n) => $n->isVisibleBy($guardian)));
    }

    public function findRecentForGuardian(Guardian $guardian, int $limit = 10): array
    {
        $notes = $this->createQueryBuilder('n')
            ->join('n.child', 'c')
            ->join('c.childGuardians', 'cg')
            ->leftJoin('n.attachments', 'a')
            ->addSelect('a')
            ->where('cg.guardian = :guardian')
            ->andWhere('cg.inviteAccepted = true')
            ->setParameter('guardian', $guardian)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit * 2)
            ->getQuery()->getResult();

        $filtered = array_filter($notes, fn(Note $n) => $n->isVisibleBy($guardian));
        return array_slice(array_values($filtered), 0, $limit);
    }
}

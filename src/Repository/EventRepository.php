<?php
namespace App\Repository;

use App\Entity\Child;
use App\Entity\Event;
use App\Entity\Guardian;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /** Événements pour FullCalendar — filtrés par visibilité, inclut les multi-jours */
    public function findForCalendar(Child $child, Guardian $guardian, ?\DateTime $start, ?\DateTime $end): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.image', 'img')
            ->addSelect('img')
            ->where('e.child = :child')
            ->setParameter('child', $child);

        if ($start && $end) {
            // Inclure les événements dont la période chevauche la fenêtre :
            // startAt <= fin_fenêtre ET (endAt >= début_fenêtre OU endAt IS NULL)
            $qb->andWhere('e.startAt <= :end')
               ->andWhere('e.endAt >= :start OR e.endAt IS NULL OR e.startAt >= :start')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        } elseif ($start) {
            $qb->andWhere('e.startAt >= :start')->setParameter('start', $start);
        } elseif ($end) {
            $qb->andWhere('e.startAt <= :end')->setParameter('end', $end);
        }

        $events = $qb->orderBy('e.startAt', 'ASC')->getQuery()->getResult();

        $result = [];
        foreach ($events as $event) {
            if ($event->isVisibleBy($guardian)) {
                $result[] = $event;
            } else {
                // Placeholder "Occupé" — pas de titre ni image sensible
                $busy = new Event();
                $busy->setChild($child);
                $busy->setTitle('Occupé');
                $busy->setType(Event::TYPE_AUTRE);
                $busy->setStartAt(clone $event->getStartAt());
                if ($event->getEndAt()) $busy->setEndAt(clone $event->getEndAt());
                $busy->setAllDay($event->isAllDay());
                $result[] = $busy;
            }
        }
        return $result;
    }

    /** Prochains événements pour le dashboard */
    public function findUpcomingForGuardian(Guardian $guardian, int $days = 7): array
    {
        $now = new \DateTime();
        $end = new \DateTime("+{$days} days");

        // On passe par les ChildGuardians pour avoir tous les enfants du gardien
        $events = $this->createQueryBuilder('e')
            ->join('e.child', 'c')
            ->join('c.childGuardians', 'cg')
            ->where('cg.guardian = :guardian')
            ->andWhere('cg.inviteAccepted = true')
            ->andWhere('e.startAt BETWEEN :now AND :end')
            ->setParameter('guardian', $guardian)
            ->setParameter('now', $now)
            ->setParameter('end', $end)
            ->orderBy('e.startAt', 'ASC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        return array_filter($events, fn(Event $e) => $e->isVisibleBy($guardian));
    }

    /** Événements pour la vue "Partagé avec moi" */
    public function findSharedForGuardian(Guardian $guardian, int $pastDays = 30): array
    {
        $from = new \DateTime("-{$pastDays} days");

        $events = $this->createQueryBuilder('e')
            ->join('e.child', 'c')
            ->join('c.childGuardians', 'cg')
            ->where('cg.guardian = :guardian')
            ->andWhere('cg.inviteAccepted = true')
            ->andWhere('e.startAt >= :from')
            ->setParameter('guardian', $guardian)
            ->setParameter('from', $from)
            ->orderBy('e.startAt', 'DESC')
            ->getQuery()->getResult();

        return array_filter($events, fn(Event $e) => $e->isVisibleBy($guardian));
    }

    /** Tous les événements d'aujourd'hui pour les enfants */
    public function findTodayGuardiansForChildren(array $children, Guardian $currentGuardian): array
    {
        if (empty($children)) return [];

        $today    = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->createQueryBuilder('e')
            ->leftJoin('e.image', 'img')->addSelect('img')
            ->where('e.child IN (:children)')
            ->andWhere('e.startAt < :tomorrow')
            ->andWhere('e.endAt >= :today OR e.startAt >= :today')
            ->setParameter('children', $children)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** Événements publics pour le magic link — visible_to NULL uniquement (pas les événements restreints) */
    public function findPublicForCalendar(\App\Entity\Child $child, \DateTime $start, \DateTime $end): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.image', 'img')->addSelect('img')
            ->where('e.child = :child')
            ->andWhere('e.startAt <= :end')
            ->andWhere('e.endAt >= :start OR e.endAt IS NULL OR e.startAt >= :start')
            ->andWhere('e.visibleTo IS NULL') // seulement les événements visibles par tous
            ->setParameter('child', $child)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('e.startAt', 'ASC')
            ->getQuery()->getResult();
    }
}

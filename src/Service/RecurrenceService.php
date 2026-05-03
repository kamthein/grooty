<?php
namespace App\Service;

use App\Entity\Child;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;

class RecurrenceService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function generateOccurrences(Event $baseEvent): int
    {
        if ($baseEvent->getRecurrence() === Event::RECURRENCE_NONE) return 0;
        if (!$baseEvent->getRecurrenceEndAt()) return 0;

        $groupId = $baseEvent->getRecurrenceGroupId() ?? bin2hex(random_bytes(16));
        $baseEvent->setRecurrenceGroupId($groupId);

        $start    = clone $baseEvent->getStartAt();
        $end      = $baseEvent->getEndAt() ? clone $baseEvent->getEndAt() : null;
        $duration = ($end && $start < $end) ? $start->diff($end) : null;
        $limit    = new \DateTime($baseEvent->getRecurrenceEndAt()->format('Y-m-d') . ' 23:59:59');

        $count     = 0;
        $maxEvents = 365;

        $current = clone $start;
        $this->advance($current, $baseEvent->getRecurrence());

        while ($current <= $limit && $count < $maxEvents) {
            $occurrence = new Event();
            $occurrence->setChild($baseEvent->getChild());
            $occurrence->setTitle($baseEvent->getTitle());
            $occurrence->setType($baseEvent->getType());
            $occurrence->setDescription($baseEvent->getDescription());
            $occurrence->setAllDay($baseEvent->isAllDay());
            $occurrence->setRecurrence(Event::RECURRENCE_NONE);
            $occurrence->setRecurrenceGroupId($groupId);
            $occurrence->setVisibleTo($baseEvent->getVisibleTo());
            if ($baseEvent->getCreatedBy()) {
                $occurrence->setCreatedBy($baseEvent->getCreatedBy());
            }

            $occStart = clone $current;
            $occurrence->setStartAt($occStart);

            if ($duration) {
                $occEnd = clone $occStart;
                $occEnd->add($duration);
                $occurrence->setEndAt($occEnd);
            }

            $this->em->persist($occurrence);
            $count++;
            $this->advance($current, $baseEvent->getRecurrence());
        }

        return $count;
    }

    public function duplicateForChild(Event $baseEvent, Child $child): Event
    {
        $copy = new Event();
        $copy->setChild($child);
        $copy->setTitle($baseEvent->getTitle());
        $copy->setType($baseEvent->getType());
        $copy->setDescription($baseEvent->getDescription());
        $copy->setAllDay($baseEvent->isAllDay());
        $copy->setStartAt(clone $baseEvent->getStartAt());
        $copy->setEndAt($baseEvent->getEndAt() ? clone $baseEvent->getEndAt() : null);
        $copy->setRecurrence($baseEvent->getRecurrence());
        $copy->setRecurrenceEndAt($baseEvent->getRecurrenceEndAt());
        if ($baseEvent->getCreatedBy()) { $copy->setCreatedBy($baseEvent->getCreatedBy()); }
        $copy->setVisibleTo(null);
        return $copy;
    }

    private function advance(\DateTime $date, string $recurrence): void
    {
        match($recurrence) {
            Event::RECURRENCE_DAILY   => $date->modify('+1 day'),
            Event::RECURRENCE_WEEKLY  => $date->modify('+1 week'),
            Event::RECURRENCE_MONTHLY => $date->modify('+1 month'),
            default                   => $date,
        };
    }
}

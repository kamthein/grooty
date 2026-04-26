<?php

namespace App\Controller;

use App\Repository\ChildRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    #[Route('/share/{token}', name: 'app_share')]
    public function share(string $token, ChildRepository $childRepo, EventRepository $eventRepo): Response
    {
        $child = $childRepo->findOneBy(['shareToken' => $token]);

        if (!$child) {
            return $this->render('public/invalid.html.twig');
        }

        // 2 semaines : courante + suivante
        $weekByDay = [];
        for ($w = 0; $w <= 1; $w++) {
            $wStart = new \DateTime('monday this week');
            $wStart->modify("+{$w} weeks");
            $wEnd = clone $wStart;
            $wEnd->modify('+6 days 23:59:59');

            $events    = $eventRepo->findPublicForCalendar($child, $wStart, $wEnd);
            $weekByDay[$w] = $this->groupByDay($events);
        }

        return $this->render(
            $child->getTheme() === 'kitty' ? 'public/share_kitty.html.twig' : 'public/share.html.twig',
            ['child' => $child, 'weekByDay' => $weekByDay, 'token' => $token]
        );
    }

    private function groupByDay(array $events): array
    {
        $grouped = [];
        foreach ($events as $event) {
            $start = clone $event->getStartAt();
            $end   = $event->getEndAt() ? clone $event->getEndAt() : clone $start;
            $cur   = clone $start; $cur->setTime(0, 0, 0);
            $endD  = clone $end;   $endD->setTime(0, 0, 0);
            while ($cur <= $endD) {
                $grouped[$cur->format('Y-m-d')][] = $event;
                $cur->modify('+1 day');
            }
        }
        return $grouped;
    }
}

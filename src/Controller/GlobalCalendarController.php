<?php
namespace App\Controller;

use App\Repository\ChildGuardianRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class GlobalCalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_global_calendar')]
    public function index(ChildGuardianRepository $cgRepo, EventRepository $eventRepo): Response
    {
        $guardian = $this->getUser();
        $cgs      = $cgRepo->findByGuardian($guardian);

        // Couleurs par enfant (cycliques)
        $palette = ['#3D5A47','#C4714A','#5B7FA6','#8E6B9E','#B07D4A','#4A8A7A','#A65B5B'];

        $children = [];
        $weekByDay = [];

        foreach ($cgs as $i => $cg) {
            $child = $cg->getChild();
            $color = $palette[$i % count($palette)];

            $children[] = [
                'child' => $child,
                'color' => $color,
            ];

            // Récupérer les événements des 12 prochaines semaines
            for ($w = 0; $w <= 11; $w++) {
                $wStart = new \DateTime('monday this week');
                $wStart->modify("+{$w} weeks");
                $wEnd = clone $wStart;
                $wEnd->modify('+6 days 23:59:59');

                $events = $eventRepo->findForCalendar($child, $guardian, $wStart, $wEnd);

                foreach ($events as $event) {
                    $start = clone $event->getStartAt();
                    $end   = $event->getEndAt() ? clone $event->getEndAt() : clone $start;
                    $cur   = clone $start; $cur->setTime(0, 0, 0);
                    $endD  = clone $end;   $endD->setTime(0, 0, 0);

                    while ($cur <= $endD) {
                        $key = $cur->format('Y-m-d');
                        if (!isset($weekByDay[$w][$key])) {
                            $weekByDay[$w][$key] = [];
                        }
                        $weekByDay[$w][$key][] = [
                            'event' => $event,
                            'child' => $child,
                            'color' => $color,
                        ];
                        $cur->modify('+1 day');
                    }
                }
            }
        }

        return $this->render('calendar/global.html.twig', [
            'children'  => $children,
            'weekByDay' => $weekByDay,
        ]);
    }
}

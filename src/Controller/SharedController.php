<?php
namespace App\Controller;

use App\Repository\ChildGuardianRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class SharedController extends AbstractController
{
    #[Route('/train/{childId}', name: 'app_train')]
    public function train(int $childId, ChildGuardianRepository $cgRepo, EventRepository $eventRepo): Response
    {
        $guardian       = $this->getUser();
        $childGuardians = $cgRepo->findByGuardian($guardian);

        $child = null;
        foreach ($childGuardians as $cg) {
            if ($cg->getChild()->getId() === $childId) {
                $child = $cg->getChild();
                break;
            }
        }
        if (!$child) throw $this->createNotFoundException();

        $children  = array_map(fn($cg) => $cg->getChild(), $childGuardians);
        $weekByDay = [];

        for ($w = 0; $w <= 11; $w++) {
            $wStart = new \DateTime('monday this week');
            $wStart->modify("+{$w} weeks");
            $wEnd = clone $wStart;
            $wEnd->modify('+6 days 23:59:59');
            $events        = $eventRepo->findForCalendar($child, $guardian, $wStart, $wEnd);
            $weekByDay[$w] = $this->groupByDay($events);
        }

        return $this->render(
            $child->getTheme() === 'kitty' ? 'shared/kitty.html.twig' : 'shared/train.html.twig',
            ['child' => $child, 'children' => $children, 'weekByDay' => $weekByDay]
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

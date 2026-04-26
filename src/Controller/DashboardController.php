<?php
namespace App\Controller;

use App\Repository\ChildGuardianRepository;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(ChildGuardianRepository $cgRepo, EventRepository $eventRepo): Response
    {
        $guardian       = $this->getUser();
        $childGuardians = $cgRepo->findByGuardian($guardian);
        $children       = array_map(fn($cg) => $cg->getChild(), $childGuardians);
        $upcomingEvents = $eventRepo->findUpcomingForGuardian($guardian, 14);
        $todayEvents    = $eventRepo->findTodayGuardiansForChildren($children, $guardian);

        return $this->render('base/dashboard.html.twig', [
            'childGuardians' => $childGuardians,
            'children'       => $children,
            'upcomingEvents' => $upcomingEvents,
            'todayGuardians' => $todayEvents,
        ]);
    }
}

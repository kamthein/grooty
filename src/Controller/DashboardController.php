<?php
namespace App\Controller;

use App\Repository\ChildGuardianRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        ChildGuardianRepository $cgRepo,
        EventRepository $eventRepo,
        EntityManagerInterface $em
    ): Response {
        $guardian       = $this->getUser();
        $childGuardians = $cgRepo->findByGuardian($guardian);
        $children       = array_map(fn($cg) => $cg->getChild(), $childGuardians);
        $upcomingEvents = $eventRepo->findUpcomingForGuardian($guardian, 14);
        $todayEvents    = $eventRepo->findTodayGuardiansForChildren($children, $guardian);

        // ── Calcul du vrai step onboarding depuis l'état réel ──
        // On ne compte que les enfants dont l'utilisateur est ADMIN (créateur)
        // pas les enfants où il est simple gardien invité
        $ownedChildren = array_filter($children, function($child) use ($guardian) {
            foreach ($child->getChildGuardians() as $cg) {
                if ($cg->getGuardian() && $cg->getGuardian()->getId() === $guardian->getId() && $cg->isAdmin()) {
                    return true;
                }
            }
            return false;
        });

        $hasChild  = !empty($ownedChildren);
        $hasEvent  = false;
        $hasShared = false;

        if ($hasChild) {
            foreach ($ownedChildren as $child) {
                $events = $eventRepo->findBy(['child' => $child], null, 1);
                if (!empty($events)) { $hasEvent = true; break; }
            }
            foreach ($ownedChildren as $child) {
                if ($child->getShareToken()) { $hasShared = true; break; }
            }
        }

        $onboardingStep = 0;
        if ($hasChild)  $onboardingStep = 1;
        if ($hasEvent)  $onboardingStep = 2;
        if ($hasShared) $onboardingStep = 3;

        // Si l'utilisateur n'a aucun enfant en propriété mais est gardien invité → onboarding terminé
        if (empty($ownedChildren) && !empty($children)) {
            $onboardingStep = 3;
        }

        // Mettre à jour en base si le step calculé est supérieur au step stocké
        if ($onboardingStep > $guardian->getOnboardingStep()) {
            $guardian->setOnboardingStep($onboardingStep);
            $em->flush();
        }

        return $this->render('base/dashboard.html.twig', [
            'childGuardians' => $childGuardians,
            'children'       => $children,
            'upcomingEvents' => $upcomingEvents,
            'todayGuardians' => $todayEvents,
            'onboardingStep' => $onboardingStep,
        ]);
    }
}

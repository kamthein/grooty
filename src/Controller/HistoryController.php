<?php
namespace App\Controller;

use App\Entity\Child;
use App\Entity\EventHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class HistoryController extends AbstractController
{
    #[Route('/children/{id}/history', name: 'app_child_history', requirements: ['id' => '\d+'])]
    public function index(int $id, EntityManagerInterface $em): Response
    {
        $child = $em->find(Child::class, $id);
        if (!$child) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('CHILD_VIEW', $child);

        // Récupérer l'historique de tous les événements de cet enfant
        $history = $em->createQueryBuilder()
            ->select('h, g')
            ->from(EventHistory::class, 'h')
            ->leftJoin('h.guardian', 'g')
            ->leftJoin('h.event', 'e')
            ->where('e.child = :child OR (h.event IS NULL AND h.snapshot IS NOT NULL)')
            ->setParameter('child', $child)
            ->orderBy('h.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()->getResult();

        return $this->render('child/history.html.twig', [
            'child'   => $child,
            'history' => $history,
        ]);
    }
}

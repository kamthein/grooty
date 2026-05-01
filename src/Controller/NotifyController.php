<?php
namespace App\Controller;

use App\Entity\Event;
use App\Repository\ChildGuardianRepository;
use App\Service\ChangeRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class NotifyController extends AbstractController
{
    #[Route('/children/{childId}/events/{id}/notify', name: 'app_event_notify', methods: ['GET', 'POST'])]
    public function notify(
        int $childId,
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ChildGuardianRepository $cgRepo,
        ChangeRequestService $changeRequestService
    ): Response {
        $action = $request->query->get('action', 'update');
        $event = $em->find(Event::class, $id);
        $child = $event?->getChild();

        // Pour delete, l'event peut ne plus exister si déjà supprimé
        // On récupère les infos depuis la session
        $deleteData = $request->getSession()->get('delete_event_' . $id);

        if (!$event && $action !== 'delete') {
            return $this->redirectToRoute('app_dashboard');
        }

        $guardian  = $this->getUser();
        $guardians = $child ? $cgRepo->findByChild($child) : [];

        // Filtrer — ne pas inclure le gardien courant
        $otherGuardians = array_filter($guardians, fn($cg) =>
            $cg->getGuardian() && $cg->getGuardian()->getId() !== $guardian->getId()
            && $cg->isInviteAccepted()
        );

        // Pré-cocher les gardiens qui voient l'événement
        $visibleTo = $event?->getVisibleTo();
        $defaultNotify = array_map(
            fn($cg) => $cg->getGuardian()->getId(),
            array_filter($otherGuardians, fn($cg) =>
                !$visibleTo || in_array($cg->getGuardian()->getId(), $visibleTo)
            )
        );

        if ($request->isMethod('POST')) {
            $notifyIds   = array_map('intval', $request->request->all('notify') ?: []);
            $validateIds = array_map('intval', $request->request->all('validate') ?: []);
            $snapshot    = $event ? $event->toSnapshot() : ($deleteData['snapshot'] ?? []);

            if ($action === 'delete' && $event) {
                // Si pas de validation → supprimer directement
                if (empty($validateIds)) {
                    $changeRequestService->handle($event, $guardian, 'delete', $snapshot, $notifyIds, []);
                    $em->remove($event);
                    $em->flush();
                    $request->getSession()->remove('delete_event_' . $id);
                    $this->addFlash('success', 'Événement supprimé.');
                    return $this->redirectToRoute('app_train', ['childId' => $childId]);
                } else {
                    // Avec validation → créer la demande
                    $changeRequestService->handle($event, $guardian, 'delete', $snapshot, $notifyIds, $validateIds);
                    $em->flush();
                    $this->addFlash('success', 'Demande de suppression envoyée pour validation.');
                    return $this->redirectToRoute('app_train', ['childId' => $childId]);
                }
            }

            $changeRequestService->handle($event, $guardian, $action, $snapshot, $notifyIds, $validateIds);
            $em->flush();

            $msg = empty($validateIds) ? 'Événement enregistré.' : 'Modification envoyée pour validation.';
            $this->addFlash('success', $msg);
            return $this->redirectToRoute('app_train', ['childId' => $childId]);
        }

        return $this->render('event/notify.html.twig', [
            'event'           => $event,
            'child'           => $child,
            'action'          => $action,
            'otherGuardians'  => $otherGuardians,
            'defaultNotify'   => $defaultNotify,
            'childId'         => $childId,
        ]);
    }
}

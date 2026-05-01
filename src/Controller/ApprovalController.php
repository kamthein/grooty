<?php
namespace App\Controller;

use App\Entity\EventChangeApproval;
use App\Service\ChangeRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApprovalController extends AbstractController
{
    #[Route('/event/approval/{token}/{response}', name: 'app_event_approval', requirements: ['response' => 'approve|reject'])]
    public function handle(
        string $token,
        string $response,
        EntityManagerInterface $em,
        ChangeRequestService $changeRequestService
    ): Response {
        $approval = $em->getRepository(EventChangeApproval::class)->findOneBy(['token' => $token]);

        if (!$approval) {
            return $this->render('approval/invalid.html.twig');
        }

        if (!$approval->isPending()) {
            return $this->render('approval/already_responded.html.twig', [
                'status' => $approval->getStatus(),
            ]);
        }

        $status = $response === 'approve' ? 'approved' : 'rejected';
        $changeRequestService->processResponse($approval, $status);

        return $this->render('approval/response.html.twig', [
            'status'  => $status,
            'event'   => $approval->getChangeRequest()->getEvent(),
            'request' => $approval->getChangeRequest(),
        ]);
    }
}

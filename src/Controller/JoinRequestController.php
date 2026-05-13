<?php

namespace App\Controller;

use App\Entity\ChildGuardian;
use App\Repository\JoinRequestRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JoinRequestController extends AbstractController
{
    #[Route('/join-request/{id}/accept', name: 'app_join_request_accept')]
    public function accept(
        int $id,
        Request $request,
        JoinRequestRepository $joinReqRepo,
        EntityManagerInterface $em,
        NotificationService $notifier,
    ): Response {
        $req = $joinReqRepo->find($id);

        if (!$req || $req->getStatus() !== 'pending' || $req->getToken() !== $request->query->get('token')) {
            $this->addFlash('error', 'Lien invalide ou expiré.');
            return $this->redirectToRoute('app_dashboard');
        }

        $cg = (new ChildGuardian())
            ->setChild($req->getChild())
            ->setGuardian($req->getRequester())
            ->setRole('other')
            ->setPermission('view')
            ->setInviteAccepted(true)
            ->setJoinedAt(new \DateTimeImmutable());
        $em->persist($cg);

        $req->setStatus('accepted');
        $em->flush();

        $notifier->sendJoinRequestAccepted($req);

        $name = $req->getRequester()->getFullName();
        $this->addFlash('success', "{$name} a bien été ajouté(e) comme gardien.");
        return $this->redirectToRoute('app_train', ['childId' => $req->getChild()->getId()]);
    }

    #[Route('/join-request/{id}/refuse', name: 'app_join_request_refuse')]
    public function refuse(
        int $id,
        Request $request,
        JoinRequestRepository $joinReqRepo,
        EntityManagerInterface $em,
        NotificationService $notifier,
    ): Response {
        $req = $joinReqRepo->find($id);

        if (!$req || $req->getStatus() !== 'pending' || $req->getToken() !== $request->query->get('token')) {
            $this->addFlash('error', 'Lien invalide ou expiré.');
            return $this->redirectToRoute('app_dashboard');
        }

        $req->setStatus('refused');
        $em->flush();

        $notifier->sendJoinRequestRefused($req);

        $this->addFlash('info', 'Demande refusée.');
        return $this->redirectToRoute('app_dashboard');
    }
}
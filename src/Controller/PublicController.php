<?php

namespace App\Controller;

use App\Entity\ChildGuardian;
use App\Entity\JoinRequest;
use App\Repository\ChildGuardianRepository;
use App\Repository\ChildRepository;
use App\Repository\EventRepository;
use App\Repository\JoinRequestRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

        $weekByDay = [];
        for ($w = 0; $w <= 1; $w++) {
            $wStart = new \DateTime('monday this week');
            $wStart->modify("+{$w} weeks");
            $wEnd = clone $wStart;
            $wEnd->modify('+6 days 23:59:59');
            $events        = $eventRepo->findPublicForCalendar($child, $wStart, $wEnd);
            $weekByDay[$w] = $this->groupByDay($events);
        }

        return $this->render(
            $child->getTheme() === 'kitty' ? 'public/share_kitty.html.twig' : 'public/share.html.twig',
            ['child' => $child, 'weekByDay' => $weekByDay, 'token' => $token]
        );
    }

    #[Route('/share/{token}/join', name: 'app_share_join', methods: ['POST'])]
    public function join(
        string $token,
        Request $request,
        ChildRepository $childRepo,
        ChildGuardianRepository $cgRepo,
        JoinRequestRepository $joinReqRepo,
        EntityManagerInterface $em,
        NotificationService $notifier,
    ): Response {
        // Pas connecté → sauvegarder le token en session et rediriger vers l'inscription
        if (!$this->getUser()) {
            $request->getSession()->set('pending_share_token', $token);
            return $this->redirectToRoute('app_register');
        }

        if (!$this->isCsrfTokenValid('join_' . $token, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $child = $childRepo->findOneBy(['shareToken' => $token]);
        if (!$child) throw $this->createNotFoundException();

        /** @var \App\Entity\Guardian $user */
        $user = $this->getUser();

        // Déjà gardien ?
        if ($cgRepo->findOneBy(['child' => $child, 'guardian' => $user])) {
            $this->addFlash('info', 'Vous êtes déjà gardien de cet enfant.');
            return $this->redirectToRoute('app_train', ['childId' => $child->getId()]);
        }

        // Demande déjà en attente ?
        if ($joinReqRepo->findOneBy(['child' => $child, 'requester' => $user, 'status' => 'pending'])) {
            $this->addFlash('info', 'Votre demande est déjà en attente de validation.');
            return $this->redirectToRoute('app_share', ['token' => $token]);
        }

        // Créer la JoinRequest
        $req = (new JoinRequest())
            ->setChild($child)
            ->setRequester($user)
            ->setStatus('pending')
            ->setToken(bin2hex(random_bytes(32)))
            ->setCreatedAt(new \DateTimeImmutable());
        $em->persist($req);
        $em->flush();

        // Notifier tous les admins du calendrier
        foreach ($cgRepo->findBy(['child' => $child, 'permission' => 'admin']) as $adminCg) {
            $notifier->sendJoinRequestToAdmin($adminCg->getGuardian(), $req);
        }

        $this->addFlash('success', 'Demande envoyée ! Un administrateur va valider votre accès.');
        return $this->redirectToRoute('app_share', ['token' => $token]);
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
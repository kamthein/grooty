<?php
namespace App\Controller;

use App\Entity\ChildGuardian;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class InviteController extends AbstractController
{
    #[Route('/invite/accept/{token}', name: 'app_invite_accept')]
    public function accept(string $token, EntityManagerInterface $em, RequestStack $requestStack): Response
    {
        $cg = $em->getRepository(ChildGuardian::class)->findOneBy(['inviteToken' => $token]);

        if (!$cg) {
            $this->addFlash('error', "Lien d'invitation invalide ou expiré.");
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        if (!$user) {
            $requestStack->getSession()->set('pending_invite_token', $token);
            $this->addFlash('info', "Connectez-vous ou créez un compte pour rejoindre le calendrier de {$cg->getChild()->getFirstName()}.");
            return $this->redirectToRoute('app_register');
        }

        if ($cg->isInviteAccepted()) {
            $this->addFlash('info', "Vous avez déjà accepté cette invitation.");
            return $this->redirectToRoute('app_dashboard');
        }

        if ($cg->getInviteEmail() && $cg->getInviteEmail() !== $user->getEmail()) {
            $this->addFlash('error', "Ce lien est destiné à {$cg->getInviteEmail()}.");
            return $this->redirectToRoute('app_dashboard');
        }

        if (!$cg->getGuardian()) {
            $cg->setGuardian($user);
        }

        $cg->setInviteAccepted(true);
        $cg->setInviteToken(null);
        $cg->setInviteEmail(null);
        $em->flush();

        $this->addFlash('success', "Vous avez rejoint le calendrier de {$cg->getChild()->getFirstName()} ! 🎉");
        return $this->redirectToRoute('app_train', ['childId' => $cg->getChild()->getId()]);
    }
}
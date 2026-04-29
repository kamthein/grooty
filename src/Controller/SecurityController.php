<?php
namespace App\Controller;

use App\Entity\Guardian;
use App\Form\RegisterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $auth, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        if ($this->getUser()) {
            // Lier les invitations en attente pour l'utilisateur connecté
            $pending = $em->getRepository(\App\Entity\ChildGuardian::class)
                ->findBy(['inviteEmail' => $this->getUser()->getEmail(), 'inviteAccepted' => false]);
            foreach ($pending as $cg) {
                if (!$cg->getGuardian()) {
                    $cg->setGuardian($this->getUser());
                    $em->flush();
                }
            }
            return $this->redirectToRoute('app_dashboard');
        }
        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error'         => $auth->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em, Security $security): Response
    {
        if ($this->getUser()) return $this->redirectToRoute('app_dashboard');
        $guardian = new Guardian();
        $form = $this->createForm(RegisterType::class, $guardian);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $guardian->setPassword($hasher->hashPassword($guardian, $form->get('plainPassword')->getData()));
            $em->persist($guardian);

            // Lier automatiquement les invitations en attente pour cet email
            $pendingInvites = $em->getRepository(\App\Entity\ChildGuardian::class)
                ->findBy(['inviteEmail' => $guardian->getEmail(), 'inviteAccepted' => false]);
            foreach ($pendingInvites as $cg) {
                $cg->setGuardian($guardian);
                // Ne pas accepter automatiquement — la personne doit cliquer le lien
            }

            $em->flush();
            $security->login($guardian, 'form_login', 'main');
            $this->addFlash('success', 'Bienvenue sur Grooty !');

            // Si invitation en attente en session
            $pendingToken = $request->getSession()->get('pending_invite_token');
            if ($pendingToken) {
                $request->getSession()->remove('pending_invite_token');
                return $this->redirectToRoute('app_invite_accept', ['token' => $pendingToken]);
            }

            return $this->redirectToRoute('app_child_new');
        }
        return $this->render('security/register.html.twig', ['form' => $form]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void {}
}

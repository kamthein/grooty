<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\ChildGuardian;
use App\Form\ChildType;
use App\Form\InviteGuardianType;
use App\Repository\ChildGuardianRepository;
use App\Service\LocalUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/children')]
class ChildController extends AbstractController
{
    #[Route('', name: 'app_child_index')]
    public function index(ChildGuardianRepository $repo): Response
    {
        return $this->render('child/index.html.twig', [
            'childGuardians' => $repo->findByGuardian($this->getUser()),
        ]);
    }

    #[Route('/new', name: 'app_child_new')]
    public function new(Request $request, EntityManagerInterface $em, LocalUploadService $uploader): Response
    {
        $child = new Child();
        $form  = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $paths = $uploader->uploadEventImage($avatarFile);
                $child->setAvatarPath($paths['filePath']);
            }

            $cg = new ChildGuardian();
            $cg->setChild($child);
            $cg->setGuardian($this->getUser());
            $cg->setRole(ChildGuardian::ROLE_PARENT);
            $cg->setPermission(ChildGuardian::PERM_ADMIN);
            $cg->setInviteAccepted(true);

            $em->persist($child);
            $em->persist($cg);
            $em->flush();

            $this->addFlash('success', "{$child->getFirstName()} a été ajouté(e) !");
            return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
        }

        return $this->render('child/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_child_show', requirements: ['id' => '\d+'])]
    public function show(Child $child, ChildGuardianRepository $cgRepo): Response
    {
        $this->denyAccessUnlessGranted('CHILD_VIEW', $child);
        return $this->render('child/show.html.twig', [
            'child'     => $child,
            'guardians' => $cgRepo->findByChild($child),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_child_edit', requirements: ['id' => '\d+'])]
    public function edit(Child $child, Request $request, EntityManagerInterface $em, LocalUploadService $uploader): Response
    {
        $this->denyAccessUnlessGranted('CHILD_ADMIN', $child);
        $form = $this->createForm(ChildType::class, $child);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $paths = $uploader->uploadEventImage($avatarFile);
                $child->setAvatarPath($paths['filePath']);
            }
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
        }

        return $this->render('child/edit.html.twig', ['form' => $form, 'child' => $child]);
    }

    #[Route('/{id}/delete', name: 'app_child_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Child $child, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('CHILD_ADMIN', $child);

        if (!$this->isCsrfTokenValid('delete_child_' . $child->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $name = $child->getFirstName();
        $em->remove($child);
        $em->flush();

        $this->addFlash('success', "{$name} et toutes ses données ont été supprimés.");
        return $this->redirectToRoute('app_child_index');
    }

    #[Route('/{id}/share', name: 'app_child_share', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function share(Child $child, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('CHILD_VIEW', $child);

        if (!$child->getShareToken()) {
            $child->generateShareToken();
            $em->flush();
        }

        $link = $this->generateUrl('app_share', ['token' => $child->getShareToken()],
            \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $this->addFlash('success', "Lien de partage : {$link}");
        return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
    }

    #[Route('/{id}/share/revoke', name: 'app_child_share_revoke', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revokeShare(Child $child, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('CHILD_ADMIN', $child);
        $child->setShareToken(null);
        $em->flush();
        $this->addFlash('success', 'Lien de partage désactivé.');
        return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
    }

    #[Route('/{id}/invite', name: 'app_child_invite', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function invite(Child $child, Request $request, EntityManagerInterface $em, \App\Repository\GuardianRepository $guardianRepo, \App\Service\NotificationService $notificationService): Response
    {
        $this->denyAccessUnlessGranted('CHILD_ADMIN', $child);
        $form = $this->createForm(InviteGuardianType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data  = $form->getData();
            $email = $data['email'];

            $existingGuardian = $guardianRepo->findOneBy(['email' => $email]);

            $cg = new ChildGuardian();
            $cg->setChild($child);
            $cg->setRole($data['role'] ?? ChildGuardian::ROLE_PARENT);
            $cg->setPermission($data['permission'] ?? ChildGuardian::PERM_VIEW);
            $cg->setInviteToken(bin2hex(random_bytes(16)));

            if ($existingGuardian) {
                $cg->setGuardian($existingGuardian);
                $cg->setInviteAccepted(false);
            } else {
                $cg->setGuardian(null);
                $cg->setInviteEmail($email);
                $cg->setInviteAccepted(false);
            }

            $em->persist($cg);
            $em->flush();

            // Envoyer l'email d'invitation
            try {
                $notificationService->sendInvitation($cg, $this->getUser());
                $this->addFlash('success', "Invitation envoyée à {$email} par email !");
            } catch (\Exception $e) {
                $link = $this->generateUrl('app_invite_accept', ['token' => $cg->getInviteToken()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
                $this->addFlash('success', "Invitation créée. Envoyez ce lien à {$email} : {$link}");
            }

            return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
        }

        return $this->render('child/invite.html.twig', ['form' => $form, 'child' => $child]);
    }

    #[Route('/invite/accept/{token}', name: 'app_invite_accept')]
    public function acceptInvite(string $token, \App\Repository\ChildGuardianRepository $cgRepo, EntityManagerInterface $em, \Symfony\Component\HttpFoundation\RequestStack $requestStack): Response
    {
        $cg = $cgRepo->findOneBy(['inviteToken' => $token]);

        if (!$cg) {
            $this->addFlash('error', "Lien d'invitation invalide ou expiré.");
            return $this->redirectToRoute('app_login');
        }

        // Si pas connecté → sauvegarder le token en session et rediriger
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

        // Vérifier que l'email correspond
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

        $this->addFlash('success', "Vous avez rejoint le calendrier de {$cg->getChild()->getFirstName()} !");
        return $this->redirectToRoute('app_train', ['childId' => $cg->getChild()->getId()]);
    }

    #[Route('/{id}/guardians/{cgId}/remove', name: 'app_guardian_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function removeGuardian(Child $child, int $cgId, ChildGuardianRepository $cgRepo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('CHILD_ADMIN', $child);
        $cg = $cgRepo->find($cgId);
        if ($cg && $cg->getChild() === $child && !$cg->isAdmin()) {
            $em->remove($cg);
            $em->flush();
            $this->addFlash('success', 'Gardien retiré.');
        }
        return $this->redirectToRoute('app_child_show', ['id' => $child->getId()]);
    }
}

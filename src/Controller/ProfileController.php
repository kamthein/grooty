<?php

namespace App\Controller;

use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/profile')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'guardian' => $this->getUser(),
        ]);
    }

    #[Route('/edit', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $guardian = $this->getUser();
        $form = $this->createForm(ProfileType::class, $guardian);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('newPassword')->getData();
            if ($plain) {
                $guardian->setPassword($hasher->hashPassword($guardian, $plain));
            }
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', ['form' => $form]);
    }

    #[Route('/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, Security $security, \App\Repository\ChildGuardianRepository $cgRepo): Response
    {
        $guardian = $this->getUser();

        // Supprimer les enfants dont ce guardian est le seul admin
        foreach ($cgRepo->findByGuardian($guardian) as $cg) {
            if ($cg->isAdmin()) {
                $child = $cg->getChild();
                // Vérifier si d'autres admins existent
                $otherAdmins = array_filter(
                    $child->getChildGuardians()->toArray(),
                    fn($c) => $c->getId() !== $cg->getId() && $c->isAdmin()
                );
                if (empty($otherAdmins)) {
                    // Seul admin → supprimer l'enfant et tout ce qui y est lié
                    $em->remove($child);
                }
            }
        }

        $security->logout(false);
        $em->remove($guardian);
        $em->flush();

        return $this->redirectToRoute('app_login');
    }
}

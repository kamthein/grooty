<?php

namespace App\Controller;

use App\Entity\Child;
use App\Entity\Event;
use App\Form\EventType;
use App\Repository\ChildGuardianRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/children/{childId}/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventRepository $eventRepo,
        private ChildGuardianRepository $cgRepo
    ) {}

    private function getChildAndCheckAccess(int $childId): Child
    {
        $child = $this->em->find(Child::class, $childId);
        if (!$child) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('CHILD_VIEW', $child);
        return $child;
    }

    /** API JSON pour FullCalendar */
    #[Route('/api', name: 'app_event_api', methods: ['GET'])]
    public function api(int $childId, Request $request): JsonResponse
    {
        $child    = $this->getChildAndCheckAccess($childId);
        $guardian = $this->getUser();

        $start = $request->query->get('start') ? new \DateTime($request->query->get('start')) : null;
        $end   = $request->query->get('end')   ? new \DateTime($request->query->get('end'))   : null;

        $events = $this->eventRepo->findForCalendar($child, $guardian, $start, $end);

        $typeColors = [
            Event::TYPE_GARDE    => '#3D5A47',
            Event::TYPE_ACTIVITE => '#7B9E87',
            Event::TYPE_MEDICAL  => '#C4714A',
            Event::TYPE_VACANCES => '#C4A882',
            Event::TYPE_AUTRE    => '#8A8578',
        ];

        $data = array_map(fn(Event $e) => [
            'id'                => $e->getId(),
            'title'             => $e->getTitle(),
            'start'             => $e->getStartAt()->format(\DateTime::ATOM),
            'end'               => $e->getEndAt()?->format(\DateTime::ATOM),
            'allDay'            => $e->isAllDay(),
            'backgroundColor'   => $typeColors[$e->getType()] ?? '#8A8578',
            'borderColor'       => $typeColors[$e->getType()] ?? '#8A8578',
            'extendedProps'     => [
                'type'        => $e->getType(),
                'description' => $e->getDescription(),
                'responsible' => $e->getResponsibleGuardian()?->getFullName(),
                'editUrl'     => $this->generateUrl('app_event_edit', [
                    'childId' => $childId, 'id' => $e->getId()
                ]),
            ],
        ], $events);

        return new JsonResponse($data);
    }

    /** Redirige vers la vue train */
    #[Route('', name: 'app_event_index')]
    public function index(int $childId): Response
    {
        $child = $this->getChildAndCheckAccess($childId);
        $cg    = $this->cgRepo->findOneByChildAndGuardian($child, $this->getUser());

        return $this->redirectToRoute('app_train', ['childId' => $childId]);
    }

    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(
        int $childId,
        Request $request,
        \App\Repository\EventImageRepository $imageRepo,
        \App\Service\LocalUploadService $uploadService
    ): Response {
        $child = $this->getChildAndCheckAccess($childId);
        $this->denyAccessUnlessGranted('CHILD_EDIT', $child);

        $event = new Event();
        $event->setChild($child);
        $event->setCreatedBy($this->getUser());
        $event->setStartAt(new \DateTime());
        $event->setEndAt(new \DateTime('+1 hour'));

        if ($request->query->get('start')) {
            $event->setStartAt(new \DateTime($request->query->get('start')));
            $event->setEndAt(new \DateTime($request->query->get('start') . ' +1 hour'));
        }

        $guardians = $this->cgRepo->findByChild($child);
        $form = $this->createForm(EventType::class, $event, ['guardians' => $guardians]);
        $form->handleRequest($request);

        // Bibliothèque d'images existantes pour cet enfant
        $existingImages = $imageRepo->findByChild($child);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Image sélectionnée depuis la bibliothèque
            $selectedImageId = $request->request->get('selected_image_id');
            if ($selectedImageId) {
                $img = $imageRepo->find((int)$selectedImageId);
                if ($img && $img->getChild() === $child) {
                    $event->setImage($img);
                }
            }

            // 2. Nouveau fichier uploadé
            $uploadedFile = $request->files->get('event_image_file');
            if ($uploadedFile) {
                $paths = $uploadService->uploadEventImage($uploadedFile);

                $img = new \App\Entity\EventImage();
                $img->setChild($child);
                $img->setUploadedBy($this->getUser());
                $img->setFilePath($paths['filePath']);
                $img->setThumbnailPath($paths['thumbnailPath']);
                $img->setLabel($request->request->get('image_label') ?: $event->getTitle());
                $this->em->persist($img);
                $event->setImage($img);
            }

            // 3. Visibilité (champ non mappé)
            $visibleTo = $form->get('visibleTo')->getData();
            $event->setVisibleTo(empty($visibleTo) ? null : array_map('intval', $visibleTo));

            $this->em->persist($event);
            $this->em->flush();
            return $this->redirectToRoute('app_event_index', ['childId' => $childId]);
        }

        return $this->render('event/new.html.twig', [
            'form'           => $form,
            'child'          => $child,
            'event'          => $event,
            'existingImages' => $existingImages,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $childId,
        Event $event,
        Request $request,
        \App\Repository\EventImageRepository $imageRepo,
        \App\Service\LocalUploadService $uploadService
    ): Response {
        $child = $this->getChildAndCheckAccess($childId);
        $this->denyAccessUnlessGranted('CHILD_EDIT', $child);

        $guardians = $this->cgRepo->findByChild($child);
        $form = $this->createForm(EventType::class, $event, [
            'guardians'  => $guardians,
            'visible_to' => $event->getVisibleTo() ?? [],
        ]);
        $form->handleRequest($request);

        $existingImages = $imageRepo->findByChild($child);

        if ($form->isSubmitted() && $form->isValid()) {
            // Image depuis la bibliothèque
            $selectedImageId = $request->request->get('selected_image_id');
            if ($selectedImageId) {
                $img = $imageRepo->find((int)$selectedImageId);
                if ($img && $img->getChild() === $child) {
                    $event->setImage($img);
                }
            } elseif ($selectedImageId === '') {
                $event->setImage(null);
            }

            // Nouveau fichier uploadé
            $uploadedFile = $request->files->get('event_image_file');
            if ($uploadedFile) {
                $paths = $uploadService->uploadEventImage($uploadedFile);
                $img = new \App\Entity\EventImage();
                $img->setChild($child);
                $img->setUploadedBy($this->getUser());
                $img->setFilePath($paths['filePath']);
                $img->setThumbnailPath($paths['thumbnailPath']);
                $img->setLabel($request->request->get('image_label') ?: $event->getTitle());
                $this->em->persist($img);
                $event->setImage($img);
            }

            // Visibilité (champ non mappé)
            $visibleTo = $form->get('visibleTo')->getData();
            $event->setVisibleTo(empty($visibleTo) ? null : array_map('intval', $visibleTo));

            $this->em->flush();
            $this->addFlash('success', 'Événement mis à jour.');
            return $this->redirectToRoute('app_event_index', ['childId' => $childId]);
        }

        return $this->render('event/new.html.twig', [
            'form'           => $form,
            'child'          => $child,
            'event'          => $event,
            'existingImages' => $existingImages,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_event_delete', methods: ['POST'])]
    public function delete(int $childId, Event $event, Request $request): JsonResponse|Response
    {
        $child = $this->getChildAndCheckAccess($childId);
        $this->denyAccessUnlessGranted('CHILD_EDIT', $child);

        // CSRF uniquement pour les soumissions de formulaire (pas AJAX)
        if (!$request->isXmlHttpRequest()) {
            if (!$this->isCsrfTokenValid('delete_event_' . $event->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
        }

        $this->em->remove($event);
        $this->em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Événement supprimé.');
        return $this->redirectToRoute('app_event_index', ['childId' => $childId]);
    }

    /** Vue "Partagé avec moi" — tous enfants confondus */
    #[Route('/shared', name: 'app_event_shared')]
    public function shared(): Response
    {
        // Retiré du prefix /children/{childId} — route globale
        return $this->redirectToRoute('app_shared');
    }
}

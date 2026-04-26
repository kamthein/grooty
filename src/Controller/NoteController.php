<?php
namespace App\Controller;

use App\Entity\Child;
use App\Entity\Note;
use App\Repository\NoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/children/{childId}/notes')]
class NoteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private NoteRepository         $noteRepo,
    ) {}

    private function getChild(int $childId): Child
    {
        $child = $this->em->find(Child::class, $childId);
        if (!$child) throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted('CHILD_VIEW', $child);
        return $child;
    }

    /** Page notes standalone */
    #[Route('', name: 'app_note_index')]
    public function index(int $childId): Response
    {
        $child = $this->getChild($childId);
        $notes = $this->noteRepo->findForChildAndGuardian($child, $this->getUser());
        return $this->render('note/index.html.twig', ['child' => $child, 'notes' => $notes]);
    }

    /** GET JSON — liste pour le dashboard */
    #[Route('/list', name: 'app_note_list', methods: ['GET'])]
    public function list(int $childId): JsonResponse
    {
        $child = $this->getChild($childId);
        $notes = $this->noteRepo->findForChildAndGuardian($child, $this->getUser());
        return new JsonResponse(array_map(fn(Note $n) => $this->serialize($n), $notes));
    }

    /** POST AJAX — créer une note texte */
    #[Route('/new', name: 'app_note_new', methods: ['POST'])]
    public function new(int $childId, Request $request): JsonResponse
    {
        $child    = $this->getChild($childId);
        $guardian = $this->getUser();
        $this->denyAccessUnlessGranted('CHILD_EDIT', $child);

        $content = trim($request->request->get('content', ''));
        if (!$content) {
            return new JsonResponse(['success' => false, 'error' => 'Contenu vide'], 400);
        }

        $note = (new Note())
            ->setChild($child)
            ->setAuthor($guardian)
            ->setContent($content);

        $this->em->persist($note);
        $this->em->flush();

        return new JsonResponse([
            'success'     => true,
            'noteId'      => $note->getId(),
            'author'      => $guardian->getFullName(),
            'authorId'    => $guardian->getId(),
            'content'     => $note->getContent(),
            'createdAt'   => $note->getCreatedAt()->format('d/m/Y H:i'),
            'attachments' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'app_note_delete', methods: ['POST'])]
    public function delete(int $childId, Note $note): JsonResponse
    {
        $child    = $this->getChild($childId);
        $guardian = $this->getUser();
        if ($note->getChild() !== $child || $note->getAuthor() !== $guardian) {
            throw $this->createAccessDeniedException();
        }
        $this->em->remove($note);
        $this->em->flush();
        return new JsonResponse(['success' => true]);
    }

    private function serialize(Note $n): array
    {
        return [
            'id'          => $n->getId(),
            'author'      => $n->getAuthor()->getFullName(),
            'authorId'    => $n->getAuthor()->getId(),
            'content'     => $n->getContent() ?? '',
            'createdAt'   => $n->getCreatedAt()->format('d/m/Y H:i'),
            'attachments' => [],
        ];
    }
}

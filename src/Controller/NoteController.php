<?php

namespace App\Controller;

use App\Entity\AvatarNote;
use App\Repository\AvatarNoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/avatar/{key}/notes', name: 'note_', requirements: ['key' => '[0-9a-f\-]+'])]
class NoteController extends AbstractController
{
    public function __construct(
        private readonly AvatarNoteRepository $noteRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(string $key, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('note_new_' . $key, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $content = trim($request->request->getString('content'));
        if ($content === '') {
            $this->addFlash('error', 'Note content cannot be empty.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $note = (new AvatarNote())
            ->setAvatarKey($key)
            ->setContent($content)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setAuthor($this->getUser());

        $this->em->persist($note);
        $this->em->flush();

        $this->addFlash('success', 'Note added.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['POST'])]
    public function edit(string $key, AvatarNote $note, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('note_edit_' . $note->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($note->getAuthor()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can only edit your own notes.');
        }

        $content = trim($request->request->getString('content'));
        if ($content === '') {
            $this->addFlash('error', 'Note content cannot be empty.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $note->setContent($content)
             ->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        $this->addFlash('success', 'Note updated.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $key, AvatarNote $note, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('note_delete_' . $note->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($note->getAuthor()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can only delete your own notes.');
        }

        $this->em->remove($note);
        $this->em->flush();

        $this->addFlash('success', 'Note deleted.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }
}

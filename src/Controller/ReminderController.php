<?php

namespace App\Controller;

use App\Entity\AvatarReminder;
use App\Repository\AvatarReminderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/avatar/{key}/reminders', name: 'reminder_', requirements: ['key' => '[0-9a-f\-]+'])]
class ReminderController extends AbstractController
{
    public function __construct(
        private readonly AvatarReminderRepository $reminderRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/new', name: 'new', methods: ['POST'])]
    public function new(string $key, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reminder_new_' . $key, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $content = trim($request->request->getString('content'));
        if ($content === '') {
            $this->addFlash('error', 'Reminder content cannot be empty.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $reminderAtStr = $request->request->getString('reminder_at');
        try {
            $reminderAt = new \DateTimeImmutable($reminderAtStr, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            $this->addFlash('error', 'Invalid reminder date.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $reminder = (new AvatarReminder())
            ->setAvatarKey($key)
            ->setContent($content)
            ->setReminderAt($reminderAt)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setAuthor($this->getUser());

        $this->em->persist($reminder);
        $this->em->flush();

        $this->addFlash('success', 'Reminder added.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['POST'])]
    public function edit(string $key, AvatarReminder $reminder, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reminder_edit_' . $reminder->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($reminder->getAuthor()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can only edit your own reminders.');
        }

        $content = trim($request->request->getString('content'));
        if ($content === '') {
            $this->addFlash('error', 'Reminder content cannot be empty.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $reminderAtStr = $request->request->getString('reminder_at');
        try {
            $reminderAt = new \DateTimeImmutable($reminderAtStr, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            $this->addFlash('error', 'Invalid reminder date.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $reminder->setContent($content)
                 ->setReminderAt($reminderAt);

        $this->em->flush();

        $this->addFlash('success', 'Reminder updated.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $key, AvatarReminder $reminder, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reminder_delete_' . $reminder->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($reminder->getAuthor()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can only delete your own reminders.');
        }

        $this->em->remove($reminder);
        $this->em->flush();

        $this->addFlash('success', 'Reminder deleted.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }

    #[Route('/{id}/resolve', name: 'resolve', methods: ['POST'])]
    public function resolve(string $key, AvatarReminder $reminder, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reminder_resolve_' . $reminder->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($reminder->isResolved()) {
            $this->addFlash('error', 'Reminder is already resolved.');
            return $this->redirectToRoute('app_avatar', ['key' => $key]);
        }

        $reminder->setResolvedAt(new \DateTimeImmutable())
                 ->setResolvedBy($this->getUser());

        $this->em->flush();

        $this->addFlash('success', 'Reminder marked as done.');
        return $this->redirectToRoute('app_avatar', ['key' => $key]);
    }
}

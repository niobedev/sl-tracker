<?php

namespace App\EventListener;

use App\Repository\AvatarReminderRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Injects active reminder counts and preview list into Twig globals on every
 * main request for authenticated users, so base.html.twig can display the
 * navbar reminder indicator without any controller needing to pass this data.
 */
class ReminderNavbarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AvatarReminderRepository $reminderRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly Environment $twig,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController'];
    }

    public function onController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            $this->twig->addGlobal('active_reminder_count', 0);
            $this->twig->addGlobal('overdue_reminder_count', 0);
            $this->twig->addGlobal('active_reminders_preview', []);
            return;
        }

        $active  = $this->reminderRepository->findAllActive();
        $now     = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $overdue = array_filter($active, fn($r) => $r->getReminderAt() < $now);

        $this->twig->addGlobal('active_reminder_count', count($active));
        $this->twig->addGlobal('overdue_reminder_count', count($overdue));
        $this->twig->addGlobal('active_reminders_preview', array_slice($active, 0, 5));
    }
}

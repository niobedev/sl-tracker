<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\AvatarReminderRepository;
use App\Repository\EventRepository;
use App\Repository\TrackedAvatarRepository;
use App\Service\ApiAuthService;
use App\Service\NotificationService;
use App\Service\TrackingConfigService;
use App\Service\SecondLifeProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly TrackedAvatarRepository $trackedAvatarRepository,
        private readonly AvatarReminderRepository $reminderRepository,
        private readonly SecondLifeProfileService $profileService,
        private readonly ApiAuthService $apiAuthService,
        private readonly NotificationService $notificationService,
        private readonly TrackingConfigService $trackingConfigService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/live-visitors', name: 'live_visitors', methods: ['GET'])]
    public function liveVisitors(): JsonResponse
    {
        return $this->json($this->eventRepository->getLiveVisitors());
    }

    #[Route('/recent-visitors', name: 'recent_visitors', methods: ['GET'])]
    public function recentVisitors(#[MapQueryParameter] string $period = 'today'): JsonResponse
    {
        $allowed = ['today', 'yesterday', 'week', 'month', 'year', 'all'];
        if (!in_array($period, $allowed, true)) {
            $period = 'today';
        }
        return $this->json($this->eventRepository->getRecentVisitors($period));
    }

    #[Route('/leaderboard', name: 'leaderboard', methods: ['GET'])]
    public function leaderboard(#[MapQueryParameter] int $limit = 25): JsonResponse
    {
        return $this->json($this->eventRepository->getLeaderboard($limit));
    }

    #[Route('/heatmap', name: 'heatmap', methods: ['GET'])]
    public function heatmap(): JsonResponse
    {
        return $this->json($this->eventRepository->getHeatmap());
    }

    #[Route('/hourly', name: 'hourly', methods: ['GET'])]
    public function hourly(): JsonResponse
    {
        return $this->json($this->eventRepository->getHourlyHistogram());
    }

    #[Route('/daily', name: 'daily', methods: ['GET'])]
    public function daily(): JsonResponse
    {
        return $this->json($this->eventRepository->getDailyStats());
    }

    #[Route('/weekday', name: 'weekday', methods: ['GET'])]
    public function weekday(): JsonResponse
    {
        return $this->json($this->eventRepository->getDayOfWeekStats());
    }

    #[Route('/concurrent', name: 'concurrent', methods: ['GET'])]
    public function concurrent(#[MapQueryParameter] int $days = 90): JsonResponse
    {
        return $this->json($this->eventRepository->getConcurrentPresence($days));
    }

    #[Route('/duration-distribution', name: 'duration_distribution', methods: ['GET'])]
    public function durationDistribution(): JsonResponse
    {
        return $this->json($this->eventRepository->getDurationDistribution());
    }

    #[Route('/frequency-vs-duration', name: 'frequency_vs_duration', methods: ['GET'])]
    public function frequencyVsDuration(): JsonResponse
    {
        return $this->json($this->eventRepository->getFrequencyVsDuration());
    }

    #[Route('/new-vs-returning', name: 'new_vs_returning', methods: ['GET'])]
    public function newVsReturning(): JsonResponse
    {
        return $this->json($this->eventRepository->getNewVsReturning());
    }

    /**
     * Background profile refresh endpoint — fetches fresh data from SL and returns it as JSON.
     * Called by the avatar page JS when the cached profile is stale (stale-while-revalidate).
     */
    #[Route('/avatar/{key}/profile', name: 'avatar_profile', methods: ['GET'], requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatarProfile(string $key): JsonResponse
    {
        $profile = $this->profileService->fetchProfile($key, forceRefresh: true);
        if ($profile === null) {
            return $this->json(['error' => 'Profile not available'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'bio_html'  => $profile['bioHtml'],
            'image_url' => $profile['imageUrl'],
            'synced_at' => $profile['syncedAt']->getTimestamp(),
        ]);
    }

    #[Route('/avatar/{key}', name: 'avatar', methods: ['GET'], requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatar(string $key): JsonResponse
    {
        $stats = $this->eventRepository->getAvatarStats($key);
        
        if (!$stats) {
            // Avatar has no events yet - return empty stats
            $stats = [
                'avatar_key' => strtolower($key),
                'display_name' => '',
                'username' => '',
                'visit_count' => 0,
                'total_minutes' => 0,
                'avg_minutes' => 0,
                'first_visit' => null,
                'last_visit' => null,
            ];
        }

        return $this->json([
            'stats' => $stats,
            'hourly' => $this->eventRepository->getAvatarHourlyHistogram($key),
            'history' => $this->eventRepository->getAvatarVisitHistory($key),
        ]);
    }

    #[Route('/reminders/active', name: 'reminders_active', methods: ['GET'])]
    public function remindersActive(): JsonResponse
    {
        $reminders = $this->reminderRepository->findAllActive();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $this->json(array_map(fn($r) => [
            'id'          => $r->getId(),
            'avatar_key'  => $r->getAvatarKey(),
            'content'     => $r->getContent(),
            'reminder_at' => $r->getReminderAt()->getTimestamp(),
            'is_overdue'  => $r->getReminderAt() < $now,
            'author'      => $r->getAuthor()->getUsername(),
        ], $reminders));
    }

    #[Route('/events', name: 'events', methods: ['POST'])]
    public function receiveEvents(Request $request): JsonResponse
    {
        if (!$this->apiAuthService->validateApiKey($request)) {
            return $this->json(['error' => 'Invalid API key'], Response::HTTP_UNAUTHORIZED);
        }

        $events = json_decode($request->getContent(), true);
        if (!is_array($events)) {
            return $this->json(['error' => 'Invalid request body'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($events)) {
            return $this->json(['error' => 'No events provided'], Response::HTTP_BAD_REQUEST);
        }

        $hasErrors = false;
        $received = 0;
        foreach ($events as $event) {
            $result = $this->processEvent($event);
            if ($result === true) {
                $received++;
            } elseif ($result === false) {
                $hasErrors = true;
            }
        }

        $this->em->flush();

        if ($hasErrors && $received === 0) {
            return $this->json(['error' => 'All events failed validation'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['received' => $received], Response::HTTP_CREATED);
    }

    #[Route('/tracking-config', name: 'tracking_config', methods: ['GET'])]
    public function getTrackingConfig(Request $request): JsonResponse
    {
        if (!$this->apiAuthService->validateApiKey($request)) {
            return $this->json(['error' => 'Invalid API key'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($this->trackingConfigService->getConfig());
    }

    private function processEvent(array $event): bool
    {
        $requiredFields = ['event_ts', 'action', 'avatarKey', 'displayName', 'username'];
        foreach ($requiredFields as $field) {
            if (!isset($event[$field])) {
                return false;
            }
        }

        $action = strtolower($event['action']);
        if (!in_array($action, ['login', 'logout'], true)) {
            return false;
        }

        $avatarKey = strtolower($event['avatarKey']);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $avatarKey)) {
            return false;
        }

        try {
            $eventTs = new \DateTimeImmutable($event['event_ts']);
        } catch (\Exception) {
            return false;
        }

        $entity = new Event();
        $entity->setAction($action);
        $entity->setAvatarKey($avatarKey);
        $entity->setDisplayName($event['displayName']);
        $entity->setUsername($event['username']);
        $entity->setEventTs($eventTs);
        $entity->setRegionName($event['regionName'] ?? 'global');
        $entity->setPosition(isset($event['position']) ? json_encode($event['position']) : null);

        $this->em->persist($entity);

        $tracked = $this->trackedAvatarRepository->find($avatarKey);
        if ($tracked && $tracked->isTrackingEnabled() && $tracked->getNotificationChannel()) {
            try {
                $notifier = $this->notificationService->getNotifier(
                    $tracked->getNotificationChannel()->getType()
                );

                if ($action === 'login') {
                    $notifier->sendLogin($tracked, $event);
                } else {
                    $notifier->sendLogout($tracked, $event);
                }
            } catch (\Throwable $e) {
                error_log("Notification failed: " . $e->getMessage());
            }
        }

        return true;
    }
}

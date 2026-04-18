<?php

namespace App\Controller;

use App\Service\ApiAuthService;
use App\Service\AvatarTrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TrackedAvatarRepository;

#[Route('/api/avatars', name: 'api_avatars_')]
class AvatarController extends AbstractController
{
    public function __construct(
        private readonly ApiAuthService $apiAuthService,
        private readonly AvatarTrackingService $avatarTrackingService,
        private readonly TrackedAvatarRepository $trackedAvatarRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    private function validateApi(Request $request): ?JsonResponse
    {
        if (!$this->apiAuthService->validateApiKey($request)) {
            return $this->json(['error' => 'Invalid API key'], Response::HTTP_UNAUTHORIZED);
        }
        return null;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $error = $this->validateApi($request);
        if ($error) return $error;

        $avatars = $this->trackedAvatarRepository->findAllWithProfile();
        $data = array_map(function ($avatar) {
            return [
                'id' => $avatar->getId(),
                'avatarKey' => $avatar->getAvatarKey(),
                'trackingEnabled' => $avatar->isTrackingEnabled(),
                'notificationChannel' => $avatar->getNotificationChannel() ? [
                    'id' => $avatar->getNotificationChannel()->getId(),
                    'name' => $avatar->getNotificationChannel()->getName(),
                    'type' => $avatar->getNotificationChannel()->getType(),
                ] : null,
                'createdAt' => $avatar->getCreatedAt()->getTimestamp(),
                'updatedAt' => $avatar->getUpdatedAt()->getTimestamp(),
            ];
        }, $avatars);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $error = $this->validateApi($request);
        if ($error) return $error;

        $data = json_decode($request->getContent(), true);
        if (!isset($data['avatarKey'])) {
            return $this->json(['error' => 'avatarKey is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $avatar = $this->avatarTrackingService->addAvatar($data['avatarKey']);
            return $this->json([
                'id' => $avatar->getId(),
                'avatarKey' => $avatar->getAvatarKey(),
                'trackingEnabled' => $avatar->isTrackingEnabled(),
                'createdAt' => $avatar->getCreatedAt()->getTimestamp(),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException|\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Avatar is already being tracked'], Response::HTTP_CONFLICT);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/{key}', name: 'delete', methods: ['DELETE'], requirements: ['key' => '[0-9a-f\-]+'])]
    public function delete(Request $request, string $key): JsonResponse
    {
        $error = $this->validateApi($request);
        if ($error) return $error;

        try {
            $this->avatarTrackingService->removeAvatar(strtolower($key));
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{key}', name: 'update', methods: ['PATCH'], requirements: ['key' => '[0-9a-f\-]+'])]
    public function update(Request $request, string $key): JsonResponse
    {
        $error = $this->validateApi($request);
        if ($error) return $error;

        $data = json_decode($request->getContent(), true);
        $avatar = $this->trackedAvatarRepository->find(strtolower($key));

        if (!$avatar) {
            return $this->json(['error' => 'Avatar not found'], Response::HTTP_NOT_FOUND);
        }

        $lowerKey = strtolower($key);
        if (isset($data['trackingEnabled'])) {
            $this->avatarTrackingService->toggleTracking($lowerKey, (bool)$data['trackingEnabled']);
        }

        if (array_key_exists('notificationChannelId', $data)) {
            $this->avatarTrackingService->setNotificationChannel($lowerKey, $data['notificationChannelId'] ?? null);
        }

        $this->em->refresh($avatar);

        return $this->json([
            'id' => $avatar->getId(),
            'avatarKey' => $avatar->getAvatarKey(),
            'trackingEnabled' => $avatar->isTrackingEnabled(),
            'notificationChannel' => $avatar->getNotificationChannel() ? [
                'id' => $avatar->getNotificationChannel()->getId(),
                'name' => $avatar->getNotificationChannel()->getName(),
                'type' => $avatar->getNotificationChannel()->getType(),
            ] : null,
            'updatedAt' => $avatar->getUpdatedAt()->getTimestamp(),
        ]);
    }
}

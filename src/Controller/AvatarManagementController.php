<?php

namespace App\Controller;

use App\Service\AvatarTrackingService;
use App\Repository\NotificationChannelRepository;
use App\Repository\AvatarProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/avatars', name: 'app_avatars_')]
class AvatarManagementController extends AbstractController
{
    public function __construct(
        private readonly AvatarTrackingService $avatarTrackingService,
        private readonly NotificationChannelRepository $channelRepository,
        private readonly AvatarProfileRepository $profileRepository,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $avatars = $this->avatarTrackingService->getAvatarsWithProfiles();
        $channels = $this->channelRepository->findAll();

        return $this->render('avatars/index.html.twig', [
            'avatars' => $avatars,
            'channels' => $channels,
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['avatarKey'])) {
            return $this->json(['error' => 'avatarKey is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $avatar = $this->avatarTrackingService->addAvatar($data['avatarKey']);
            $profile = $this->profileRepository->find($avatar->getAvatarKey());

            return $this->json([
                'id' => $avatar->getId(),
                'avatarKey' => $avatar->getAvatarKey(),
                'trackingEnabled' => $avatar->isTrackingEnabled(),
                'profile' => $profile ? [
                    'name' => $profile->getName(),
                    'username' => $profile->getUsername() ?? '',
                    'imageUrl' => $profile->getImageUrl(),
                ] : null,
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException|\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            return $this->json(['error' => 'Avatar is already being tracked', 'exception' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/{key}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $key): JsonResponse
    {
        try {
            $this->avatarTrackingService->removeAvatar($key);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{key}', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, string $key): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['trackingEnabled'])) {
            $this->avatarTrackingService->toggleTracking($key, (bool)$data['trackingEnabled']);
        }

        if (array_key_exists('notificationChannelId', $data)) {
            $this->avatarTrackingService->setNotificationChannel($key, $data['notificationChannelId'] ?? null);
        }

        $avatarData = $this->avatarTrackingService->getAvatarWithProfile($key);
        if (!$avatarData) {
            return $this->json(['error' => 'Avatar not found'], Response::HTTP_NOT_FOUND);
        }

        $avatar = $avatarData['avatar'];
        $profile = $avatarData['profile'];

        return $this->json([
            'id' => $avatar->getId(),
            'avatarKey' => $avatar->getAvatarKey(),
            'trackingEnabled' => $avatar->isTrackingEnabled(),
            'notificationChannel' => $avatar->getNotificationChannel() ? [
                'id' => $avatar->getNotificationChannel()->getId(),
                'name' => $avatar->getNotificationChannel()->getName(),
                'type' => $avatar->getNotificationChannel()->getType(),
            ] : null,
            'profile' => $profile ? [
                'name' => $profile->getName(),
                'username' => $profile->getUsername() ?? '',
                'imageUrl' => $profile->getImageUrl(),
            ] : null,
        ]);
    }
}

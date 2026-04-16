<?php

namespace App\Controller;

use App\Entity\NotificationChannel;
use App\Repository\NotificationChannelRepository;
use App\Service\ApiAuthService;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/notification-channels', name: 'api_notification_channels_')]
class NotificationChannelController extends AbstractController
{
    public function __construct(
        private readonly ApiAuthService $apiAuthService,
        private readonly NotificationChannelRepository $channelRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    private function validateApi(Request $request): void
    {
        if (!$this->apiAuthService->validateApiKey($request)) {
            throw new AccessDeniedHttpException('Invalid API key');
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->validateApi($request);

        $channels = $this->channelRepository->findAll();
        $data = array_map(function ($channel) {
            return [
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'type' => $channel->getType(),
                'config' => $channel->getConfig(),
                'enabled' => $channel->isEnabled(),
                'createdAt' => $channel->getCreatedAt()->getTimestamp(),
                'updatedAt' => $channel->getUpdatedAt()->getTimestamp(),
            ];
        }, $channels);

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->validateApi($request);

        $data = json_decode($request->getContent(), true);
        if (!isset($data['name']) || !isset($data['type']) || !isset($data['config'])) {
            return $this->json(['error' => 'name, type, and config are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $channel = new NotificationChannel();
            $channel->setName($data['name']);
            $channel->setType($data['type']);
            $channel->setConfig($data['config']);
            $channel->setEnabled($data['enabled'] ?? true);

            $this->em->persist($channel);
            $this->em->flush();

            return $this->json([
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'type' => $channel->getType(),
                'config' => $channel->getConfig(),
                'enabled' => $channel->isEnabled(),
                'createdAt' => $channel->getCreatedAt()->getTimestamp(),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $this->validateApi($request);

        $channel = $this->channelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => 'Channel not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $channel->setName($data['name']);
        }
        if (isset($data['config'])) {
            $channel->setConfig($data['config']);
        }
        if (isset($data['enabled'])) {
            $channel->setEnabled((bool)$data['enabled']);
        }

        $this->em->flush();

        return $this->json([
            'id' => $channel->getId(),
            'name' => $channel->getName(),
            'type' => $channel->getType(),
            'config' => $channel->getConfig(),
            'enabled' => $channel->isEnabled(),
            'updatedAt' => $channel->getUpdatedAt()->getTimestamp(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, int $id): JsonResponse
    {
        $this->validateApi($request);

        $channel = $this->channelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => 'Channel not found'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($channel);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/test', name: 'test', methods: ['POST'])]
    public function test(Request $request, int $id): JsonResponse
    {
        $this->validateApi($request);

        $channel = $this->channelRepository->find($id);
        if (!$channel) {
            return $this->json(['error' => 'Channel not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $notifier = $this->notificationService->getNotifier($channel->getType());
            $success = $notifier->test($channel);
            return $this->json(['success' => $success]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

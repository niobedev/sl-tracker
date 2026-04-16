<?php

namespace App\Controller;

use App\Entity\NotificationChannel;
use App\Repository\NotificationChannelRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/notification-channels', name: 'app_notification_channels_')]
class NotificationChannelManagementController extends AbstractController
{
    public function __construct(
        private readonly NotificationChannelRepository $channelRepository,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $channels = $this->channelRepository->findAll();

        return $this->render('notification-channels/index.html.twig', [
            'channels' => $channels,
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
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
                'enabled' => $channel->isEnabled(),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['POST'])]
    public function update(Request $request, int $id): JsonResponse
    {
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
            'enabled' => $channel->isEnabled(),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
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

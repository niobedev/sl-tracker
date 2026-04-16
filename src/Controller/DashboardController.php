<?php

namespace App\Controller;

use App\Repository\AvatarNoteRepository;
use App\Repository\AvatarProfileRepository;
use App\Repository\AvatarReminderRepository;
use App\Repository\EventRepository;
use App\Service\SecondLifeProfileService;
use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly SecondLifeProfileService $slProfile,
        private readonly AvatarProfileRepository $profileRepository,
        private readonly AvatarNoteRepository $noteRepository,
        private readonly AvatarReminderRepository $reminderRepository,
        private readonly CommonMarkConverter $markdown,
    ) {}

    #[Route('/', name: 'app_dashboard')]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig');
    }

    #[Route('/avatar/{key}', name: 'app_avatar', requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatar(string $key): Response
    {
        $stats = $this->eventRepository->getAvatarStats($key);
        
        // Handle avatars with no events yet
        if (!$stats) {
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

        $notes = array_map(fn($n) => [
            'entity' => $n,
            'html'   => $this->markdown->convert($n->getContent())->getContent(),
        ], $this->noteRepository->findForAvatar($key));

        $reminders = array_map(fn($r) => [
            'entity' => $r,
            'html'   => $this->markdown->convert($r->getContent())->getContent(),
        ], array_merge(
            $this->reminderRepository->findActiveForAvatar($key),
            $this->reminderRepository->findResolvedForAvatar($key),
        ));

        return $this->render('dashboard/avatar.html.twig', [
            'stats'            => $stats,
            'avatar_key'       => $key,
            'sl_profile'       => $this->slProfile->fetchProfile($key),
            'profile_is_stale' => $this->slProfile->isStale($key),
            'notes'            => $notes,
            'reminders'        => $reminders,
        ]);
    }

    #[Route('/avatar/{key}/picture', name: 'app_avatar_picture', requirements: ['key' => '[0-9a-f\-]+'])]
    public function avatarPicture(string $key, Request $request): Response
    {
        $key      = strtolower($key);
        $imageData = $this->profileRepository->findImageData($key);

        if ($imageData === null) {
            // No cached image — redirect to SL directly as a one-time fallback
            $profile = $this->profileRepository->find($key);
            $imageUrl = $profile?->getImageUrl();
            if ($imageUrl) {
                return $this->redirect($imageUrl);
            }
            throw $this->createNotFoundException('No picture available.');
        }
        $etag      = md5($imageData);

        // Return 304 if browser already has this version
        if ($request->headers->get('If-None-Match') === $etag) {
            return new Response('', Response::HTTP_NOT_MODIFIED);
        }

        $response = new StreamedResponse(static function () use ($imageData): void {
            echo $imageData;
        });
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('Content-Length', (string) strlen($imageData));
        $response->headers->set('Cache-Control', 'public, max-age=86400');
        $response->headers->set('ETag', $etag);

        return $response;
    }
}

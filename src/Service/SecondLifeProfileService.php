<?php

namespace App\Service;

use App\Entity\AvatarProfile;
use App\Repository\AvatarProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SecondLifeProfileService
{
    private const REFRESH_AFTER = 86400; // 24 hours

    /** UUIDs currently being fetched — prevents infinite recursion on mutual SL links. */
    private array $fetching = [];

    /**
     * True when no profile is stored, or the stored one is older than REFRESH_AFTER.
     * Use this to decide whether to trigger a background refresh from the UI.
     */
    public function isStale(string $avatarKey): bool
    {
        $stored = $this->profileRepository->find(strtolower($avatarKey));
        return !$stored || $stored->getSyncedAt()->getTimestamp() < time() - self::REFRESH_AFTER;
    }

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AvatarProfileRepository $profileRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Returns an array with keys: name, bioHtml, imageUrl, syncedAt
     * Falls back to stale DB data if SL is unreachable.
     * Returns null if no data is available at all.
     */
    public function fetchProfile(string $avatarKey, bool $forceRefresh = false): ?array
    {
        $avatarKey = strtolower($avatarKey);
        $stored = $this->profileRepository->find($avatarKey);

        // If we have any stored data and are not forcing a refresh, return it immediately —
        // stale data is served as-is; the UI will trigger a background refresh via the API.
        if (!$forceRefresh && $stored) {
            return $this->toArray($stored);
        }

        // Avoid re-entrant fetches (e.g. A's bio links to B, B's bio links back to A)
        if (isset($this->fetching[$avatarKey])) {
            return $stored ? $this->toArray($stored) : null;
        }

        $this->fetching[$avatarKey] = true;
        try {
            $parsed = $this->fetchFromSL($avatarKey);
        } finally {
            unset($this->fetching[$avatarKey]);
        }

        if ($parsed === null) {
            // SL unreachable — return stale data rather than nothing
            return $stored ? $this->toArray($stored) : null;
        }

        $imageData = $parsed['imageUrl'] ? $this->downloadImage($parsed['imageUrl']) : null;

        $profile = $stored ?? new AvatarProfile();
        $profile->setAvatarKey($avatarKey);
        $profile->setName($parsed['name']);
        $profile->setUsername($parsed['username'] ?? null);
        $profile->setImageUrl($parsed['imageUrl']);
        $profile->setBioHtml($parsed['bioHtml']);
        $profile->setSyncedAt(new \DateTimeImmutable());

        // Only overwrite stored image data if we successfully downloaded a fresh copy
        if ($imageData !== null) {
            $profile->setImageData($imageData);
        }

        $this->em->persist($profile);
        $this->em->flush();

        return $this->toArray($profile);
    }

    private function toArray(AvatarProfile $profile): array
    {
        return [
            'name'     => $profile->getName(),
            'username' => $profile->getUsername(),
            'bioHtml'  => $profile->getBioHtml(),
            'imageUrl' => $profile->getImageUrl(),
            'syncedAt' => $profile->getSyncedAt(),
        ];
    }

    private function fetchFromSL(string $avatarKey): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://world.secondlife.com/resident/' . $avatarKey, [
                'timeout' => 5,
                'headers' => ['Accept' => 'text/html'],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            return $this->parseProfileHtml($response->getContent());
        } catch (\Throwable) {
            return null;
        }
    }

    private function downloadImage(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            return $response->getContent();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseProfileHtml(string $html): ?array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);

        $meta = [];
        foreach ($xpath->query('//meta[@name]') as $node) {
            $meta[$node->getAttribute('name')] = $node->getAttribute('content');
        }

        $imageId = $meta['imageid'] ?? null;
        $rawBio  = trim($meta['description'] ?? '');

        // Title format: "Display Name (username)" or just "Display Name"
        $titleNode = $xpath->query('//title')->item(0);
        $titleText = $titleNode ? trim($titleNode->textContent) : '';

        $name = '';
        $username = '';

        if ($titleText) {
            // Try to match "Display Name (username)" pattern
            if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $titleText, $matches)) {
                $name = trim($matches[1]);
                $username = trim($matches[2]);
            } else {
                // No username in parentheses, use the whole title as name
                $name = $titleText;
            }
        }

        if (!$imageId && !$rawBio && !$name) {
            return null;
        }

        $imageUrl = $imageId ? "https://picture-service.secondlife.com/{$imageId}/256x192.jpg" : null;
        $bioHtml  = $rawBio ? $this->formatBio($rawBio) : null;

        return [
            'name'     => $name ?: null,
            'username' => $username ?: null,
            'bioHtml'  => $bioHtml,
            'imageUrl' => $imageUrl,
        ];
    }

    /**
     * Convert raw SL profile bio text to safe HTML:
     * - Replaces secondlife:///app/agent/{uuid}/{action} links with app profile links
     * - Replaces [url label text] wiki-style links with <a> tags
     * - Converts newlines to <br>
     */
    private function formatBio(string $bio): string
    {
        // HTML-escape first so user content is safe to output raw
        $html = htmlspecialchars($bio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // SL agent links: secondlife:///app/agent/{uuid}/{action}
        // action may be "about", "inspect", "pay", "mute", etc.
        $html = preg_replace_callback(
            '/secondlife:\/\/\/app\/agent\/([0-9a-f\-]+)\/\w+/i',
            function (array $m): string {
                $uuid    = strtolower($m[1]);
                $profile = $this->fetchProfile($uuid);
                $label   = htmlspecialchars($profile['name'] ?? $uuid, ENT_QUOTES, 'UTF-8');
                $safeId  = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
                return '<a href="/avatar/' . $safeId . '" class="text-indigo-400 hover:underline">' . $label . '</a>';
            },
            $html
        );

        // Wiki-style links: [https://example.com Link label]
        // Format: [url text...] where url has no spaces
        $html = preg_replace_callback(
            '/\[(\S+)\s+([^\]]+)\]/',
            static function (array $m): string {
                $url   = $m[1];
                $label = $m[2];
                // Only allow http/https URLs
                if (!preg_match('/^https?:\/\//i', $url)) {
                    return htmlspecialchars('[' . $m[1] . ' ' . $m[2] . ']', ENT_QUOTES, 'UTF-8');
                }
                $safeUrl   = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
                $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
                return '<a href="' . $safeUrl . '" class="text-indigo-400 hover:underline" target="_blank" rel="noopener noreferrer">' . $safeLabel . '</a>';
            },
            $html
        );

        // Preserve newlines
        return nl2br($html);
    }
}

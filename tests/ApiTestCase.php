<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\TrackedAvatar;
use App\Entity\NotificationChannel;

abstract class ApiTestCase extends WebTestCase
{
    protected static $client;
    protected EntityManagerInterface $entityManager;
    protected string $testApiKey = 'test-api-key-12345';

    protected function setUp(): void
    {
        self::$client = static::createClient();
        $kernel = self::$kernel;
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    protected function createAuthenticatedClient(): void
    {
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => 'testuser']);
        
        if (!$user) {
            $user = new User();
            $user->setUsername('testuser');
            $user->setPassword('$argon2id$v=19$m=65536,t=4,p=1$test');
            $user->setRoles(['ROLE_USER']);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        self::$client->loginUser($user);
    }

    protected function apiRequest(string $method, string $uri, ?string $content = null, bool $withApiKey = true): \Symfony\Component\BrowserKit\AbstractBrowser
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($withApiKey) {
            $headers['HTTP_X_API_KEY'] = $this->testApiKey;
        }

        self::$client->request($method, $uri, [], [], $headers, $content);
        return self::$client;
    }

    protected function assertApiResponse(int $expectedStatus, array $expectedData = null): void
    {
        $response = self::$client->getResponse();
        $this->assertEquals($expectedStatus, $response->getStatusCode());
        
        if ($expectedData !== null) {
            $data = json_decode($response->getContent(), true);
            $this->assertEquals($expectedData, $data);
        }
    }

    protected function createTestAvatar(string $key, bool $enabled = true): TrackedAvatar
    {
        $avatar = new TrackedAvatar();
        $avatar->setAvatarKey($key);
        $avatar->setTrackingEnabled($enabled);
        $this->entityManager->persist($avatar);
        $this->entityManager->flush();
        return $avatar;
    }

    protected function createTestNotificationChannel(string $name, string $type, array $config): NotificationChannel
    {
        $channel = new NotificationChannel();
        $channel->setName($name);
        $channel->setType($type);
        $channel->setConfig($config);
        $this->entityManager->persist($channel);
        $this->entityManager->flush();
        return $channel;
    }
}

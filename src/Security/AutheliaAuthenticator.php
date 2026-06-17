<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AutheliaAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
        #[Autowire(env: 'AUTHELIA_ENABLED')]
        private readonly string $enabled,
    ) {}

    public function supports(Request $request): ?bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $request->headers->has('Remote-User');
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->headers->get('Remote-User');

        return new SelfValidatingPassport(
            new UserBadge($username, function (string $username) {
                $user = $this->userRepository->findOneBy(['username' => $username]);
                if ($user === null) {
                    throw new CustomUserMessageAuthenticationException(
                        sprintf('User "%s" is not authorised to access this application.', $username)
                    );
                }
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response($exception->getMessageKey(), Response::HTTP_FORBIDDEN);
    }
}

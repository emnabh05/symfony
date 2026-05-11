<?php

namespace App\Security;

use App\Entity\User;
use App\Service\LegacyUserBridgeService;
use App\Service\UserPasswordCompatibilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $em,
        private LegacyUserBridgeService $legacyUserBridge,
        private UserPasswordCompatibilityService $passwordCompatibility
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $session = $request->getSession();
        $expectedAnswer = trim((string) $session->get('login_challenge_answer', ''));
        $providedAnswer = trim($request->getPayload()->getString('anti_robot_answer'));
        $session->remove('login_challenge_answer');
        $session->remove('login_challenge_question');

        if ($expectedAnswer === '' || $providedAnswer === '' || !hash_equals($expectedAnswer, $providedAnswer)) {
            throw new CustomUserMessageAuthenticationException('Verification anti-robot invalide.');
        }

        $identifier = trim($request->getPayload()->getString('email'));
        if ($identifier === '') {
            throw new CustomUserMessageAuthenticationException('Email or username is required.');
        }

        $normalizedIdentifier = mb_strtolower($identifier);
        $session->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        $password = $request->getPayload()->getString('password');

        return new Passport(
            new UserBadge($normalizedIdentifier, function () use ($normalizedIdentifier): UserInterface {
                try {
                    $user = $this->em->getRepository(User::class)
                        ->createQueryBuilder('u')
                        ->where('LOWER(u.email) = :identifier')
                        ->orWhere('LOWER(u.username) = :identifier')
                        ->setParameter('identifier', $normalizedIdentifier)
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if (!$user instanceof User) {
                        $user = $this->legacyUserBridge->findOrImportByIdentifier($normalizedIdentifier);
                    }
                } catch (\Throwable) {
                    throw new CustomUserMessageAuthenticationException('Database is unavailable. Please try again in a moment.');
                }

                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Account not found for this email/username.');
                }

                return $user;
            }),
            new CustomCredentials(function (string $plainPassword, mixed $user): bool {
                if (!$user instanceof User) {
                    return false;
                }

                try {
                    return $this->passwordCompatibility->verifyAndUpgrade($user, $plainPassword);
                } catch (\Throwable) {
                    throw new CustomUserMessageAuthenticationException('Database is unavailable. Please try again in a moment.');
                }
            }, $password),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $request->getSession()->set('2fa_verified', true);
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('diet_planner'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

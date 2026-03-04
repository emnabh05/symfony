<?php

namespace App\Security;

use App\Entity\User;
use App\Service\RecaptchaVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
        private RecaptchaVerifier $recaptchaVerifier
    )
    {
    }

    public function authenticate(Request $request): Passport
    {
        $recaptchaToken = (string) $request->request->get('g-recaptcha-response', '');
        if (
            $this->recaptchaVerifier->isConfigured()
            && $recaptchaToken !== ''
            && !$this->recaptchaVerifier->verify($recaptchaToken, $request->getClientIp())
        ) {
            throw new CustomUserMessageAuthenticationException('Captcha invalide. Veuillez confirmer "Je ne suis pas un robot".');
        }

        $email = strtolower(trim($request->getPayload()->getString('email')));

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        $password = $request->getPayload()->getString('password');

        return new Passport(
            new UserBadge($email, function (string $identifier): User {
                $user = $this->em->getRepository(User::class)
                    ->createQueryBuilder('u')
                    ->where('u.email = :identifier')
                    ->orWhere('u.username = :identifier')
                    ->setParameter('identifier', $identifier)
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Account not found for this email/username.');
                }

                return $user;
            }),
            new CustomCredentials(function (string $plainPassword, mixed $user): bool {
                if (!$user instanceof User) {
                    return false;
                }

                if ($this->passwordHasher->isPasswordValid($user, $plainPassword)) {
                    return true;
                }

                $storedPassword = (string) $user->getPassword();

                // Native password_hash()/password_verify() fallback for legacy bcrypt/argon hashes.
                if ($storedPassword !== '' && password_verify($plainPassword, $storedPassword)) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }

                if ($storedPassword !== '' && hash_equals($storedPassword, $plainPassword)) {
                    // Legacy plain-text password fallback: upgrade to a secure hash on successful login.
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }

                // Legacy crypt() fallback (old PHP hashes).
                $legacyCrypt = @crypt($plainPassword, $storedPassword);
                if (is_string($legacyCrypt) && $legacyCrypt !== '' && hash_equals($storedPassword, $legacyCrypt)) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }

                // Legacy md5/sha1/sha256 hex hashes fallback.
                if (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), md5($plainPassword))) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }
                if (preg_match('/^[a-f0-9]{40}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), sha1($plainPassword))) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }
                if (preg_match('/^[a-f0-9]{64}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), hash('sha256', $plainPassword))) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }
                if (preg_match('/^[a-f0-9]{128}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), hash('sha512', $plainPassword))) {
                    $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
                    $this->em->flush();
                    return true;
                }

                return false;
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
            $session = $request->getSession();
            if ($user->isTwoFactorEnabled() && ($user->getTwoFactorSecret() ?? '') !== '') {
                $session->set('2fa_pending_user_id', (int) $user->getId());
                $session->set('2fa_verified', false);
                $session->set('2fa_attempts', 0);
                return new RedirectResponse($this->urlGenerator->generate('app_2fa_check'));
            }
            $session->remove('2fa_pending_user_id');
            $session->set('2fa_verified', true);
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_profile'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

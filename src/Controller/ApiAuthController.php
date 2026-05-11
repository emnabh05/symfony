<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\LegacyJwtService;
use App\Service\LegacyUserBridgeService;
use App\Service\UserPasswordCompatibilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class ApiAuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly LegacyUserBridgeService $legacyUserBridge,
        private readonly UserPasswordCompatibilityService $passwordCompatibility,
        private readonly LegacyJwtService $jwtService
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $email = $this->string($payload, 'email');
            $username = $this->string($payload, 'username');

            if ($this->string($payload, 'firstName') === '' || $this->string($payload, 'lastName') === '' || $username === '' || $email === '' || $this->string($payload, 'password') === '') {
                return $this->json(['message' => 'Nom, prenom, username, email et mot de passe sont obligatoires.'], 400);
            }

            $existing = $this->legacyUserBridge->findOrImportByIdentifier($email);
            if ($existing instanceof User) {
                return $this->json(['message' => 'Un compte existe deja avec cet email.'], 409);
            }

            $user = (new User())
                ->setFirstName($this->string($payload, 'firstName'))
                ->setLastName($this->string($payload, 'lastName'))
                ->setUsername($username)
                ->setEmail($email)
                ->setBirthDate($this->stringOrNull($payload, 'birthDate'))
                ->setRole($this->stringOrNull($payload, 'role') ?? 'Patient')
                ->setPhone($this->stringOrNull($payload, 'phone'))
                ->setGender($this->stringOrNull($payload, 'gender') ?? 'male')
                ->setBio($this->stringOrNull($payload, 'bio'))
                ->setAccountStatus('ACTIVE');

            $plainPassword = $this->string($payload, 'password');
            $security = $this->passwordSecuritySnapshot($plainPassword);
            $user
                ->setPassword($this->hasher->hashPassword($user, $plainPassword))
                ->setPasswordScore($security['score'])
                ->setPasswordStrength($security['label'])
                ->setCompromisedPassword(false)
                ->setCompromisedOccurrences(0)
                ->setFailedLoginAttempts(0)
                ->setRiskScore($security['riskScore'])
                ->setPasswordLastChangedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'))
                ->setLockedUntil(null)
                ->setLastLoginAt(null)
                ->setLastFailedLoginAt(null)
                ->setArchived(false)
                ->setArchivedAt(null);

            $this->em->persist($user);
            $this->em->flush();
            $this->legacyUserBridge->syncUser($user);

            return $this->json([
                'message' => 'User created',
                'userId' => $user->getId(),
                'passwordScore' => $user->getPasswordScore(),
                'passwordStrength' => $user->getPasswordStrength(),
                'compromised' => $user->isCompromisedPassword(),
                'riskScore' => $user->getRiskScore(),
                'accountStatus' => $user->getAccountStatus(),
            ], 201);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => $e->getMessage(),
                'status' => 'SERVICE_UNAVAILABLE',
            ], 503);
        }
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $identifier = $this->string($payload, 'identifier');
            $password = $this->string($payload, 'password');
            $user = $this->legacyUserBridge->findOrImportByIdentifier($identifier);

            if (!$user instanceof User) {
                return $this->json([
                    'status' => 'INVALID_CREDENTIALS',
                    'message' => 'Email/username ou mot de passe incorrect.',
                    'userId' => null,
                    'username' => null,
                    'email' => null,
                    'role' => null,
                    'riskScore' => null,
                    'accountStatus' => null,
                    'tokenType' => null,
                    'accessToken' => null,
                    'expiresIn' => null,
                ], 401);
            }

            if ($this->isLocked($user)) {
                return $this->json([
                    'status' => 'LOCKED',
                    'message' => 'Compte temporairement bloque jusqu\'au ' . ($user->getLockedUntil() ?? ''),
                    'userId' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole(),
                    'riskScore' => $user->getRiskScore(),
                    'accountStatus' => $user->getAccountStatus(),
                    'tokenType' => null,
                    'accessToken' => null,
                    'expiresIn' => null,
                ], 423);
            }

            if (!$this->passwordCompatibility->verifyAndUpgrade($user, $password)) {
                return $this->json([
                    'status' => 'INVALID_CREDENTIALS',
                    'message' => 'Email/username ou mot de passe incorrect.',
                    'userId' => null,
                    'username' => null,
                    'email' => null,
                    'role' => null,
                    'riskScore' => null,
                    'accountStatus' => null,
                    'tokenType' => null,
                    'accessToken' => null,
                    'expiresIn' => null,
                ], 401);
            }

            $user->setLastLoginAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
            $user->setFailedLoginAttempts(0);
            $this->em->flush();
            $this->legacyUserBridge->syncUser($user);

            $token = $this->jwtService->generateAccessToken($user);

            return $this->json([
                'status' => 'SUCCESS',
                'message' => 'Authentification reussie.',
                'userId' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'riskScore' => $user->getRiskScore(),
                'accountStatus' => $user->getAccountStatus(),
                'tokenType' => 'Bearer',
                'accessToken' => $token,
                'expiresIn' => $this->jwtService->getExpirySeconds(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'SERVICE_UNAVAILABLE',
                'message' => $e->getMessage(),
                'userId' => null,
                'username' => null,
                'email' => null,
                'role' => null,
                'riskScore' => null,
                'accountStatus' => null,
                'tokenType' => null,
                'accessToken' => null,
                'expiresIn' => null,
            ], 503);
        }
    }

    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $email = $this->string($payload, 'email');
            if ($email === '') {
                return $this->json(['message' => 'Email obligatoire.'], 400);
            }

            $code = $this->legacyUserBridge->issuePasswordResetCode($email);
            if ($code === null) {
                return $this->json(['message' => 'Aucun compte n\'est associe a cet email.'], 404);
            }

            return $this->json([
                'message' => 'Password reset code sent',
                'email' => $email,
                'debugCode' => $code,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $email = $this->string($payload, 'email');
            $otpCode = $this->string($payload, 'otpCode');
            $newPassword = $this->string($payload, 'newPassword');

            if ($email === '' || $otpCode === '' || $newPassword === '') {
                return $this->json(['message' => 'Email, otpCode et newPassword sont obligatoires.'], 400);
            }

            $user = $this->legacyUserBridge->findOrImportByIdentifier($email);
            if (!$user instanceof User) {
                return $this->json(['message' => 'Aucun compte n\'est associe a cet email.'], 404);
            }

            $ok = $this->legacyUserBridge->resetPasswordWithCode(
                $email,
                $otpCode,
                $this->hasher->hashPassword($user, $newPassword)
            );

            if (!$ok) {
                return $this->json(['message' => 'Code OTP invalide ou expire.'], 400);
            }

            return $this->json([
                'message' => 'Password updated',
                'email' => $email,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $content = trim((string) $request->getContent());
        $decoded = $content !== '' ? json_decode($content, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }

        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function string(array $payload, string $key): string
    {
        return trim((string) ($payload[$key] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringOrNull(array $payload, string $key): ?string
    {
        $value = $this->string($payload, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{score: int, label: string, riskScore: int}
     */
    private function passwordSecuritySnapshot(string $plainPassword): array
    {
        $score = min(100, strlen($plainPassword) * 6);
        if (preg_match('/[A-Z]/', $plainPassword)) {
            $score += 10;
        }
        if (preg_match('/[a-z]/', $plainPassword)) {
            $score += 10;
        }
        if (preg_match('/\d/', $plainPassword)) {
            $score += 10;
        }
        if (preg_match('/[^A-Za-z0-9]/', $plainPassword)) {
            $score += 10;
        }

        $score = min(100, $score);
        $label = $score >= 80 ? 'STRONG' : ($score >= 50 ? 'MEDIUM' : 'WEAK');

        return [
            'score' => $score,
            'label' => $label,
            'riskScore' => max(0, 100 - $score),
        ];
    }

    private function isLocked(User $user): bool
    {
        $lockedUntil = trim((string) $user->getLockedUntil());
        if ($lockedUntil === '') {
            return false;
        }

        try {
            return new \DateTimeImmutable($lockedUntil) > new \DateTimeImmutable();
        } catch (\Throwable) {
            return false;
        }
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\AppAuthenticator;
use App\Service\LegacyUserBridgeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        Security $security,
        EntityManagerInterface $em,
        LegacyUserBridgeService $legacyUserBridge
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true, true) as $error) {
                if (!$error instanceof FormError) {
                    continue;
                }
                $errors[] = trim($error->getMessage());
            }

            if ($errors) {
                $this->addFlash('error', implode(' | ', $errors));
            } else {
                $this->addFlash('error', 'Form is invalid but no errors were returned.');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $securitySnapshot = $this->passwordSecuritySnapshot($plainPassword);

            $user->setPassword(
                $hasher->hashPassword(
                    $user,
                    $plainPassword
                )
            );

            $selectedRole = (string) ($form->get('role')->getData() ?? 'ROLE_PATIENT');
            $user->setRoles([$selectedRole]);
            $user->setPasswordScore($securitySnapshot['score']);
            $user->setPasswordStrength($securitySnapshot['label']);
            $user->setCompromisedPassword(false);
            $user->setCompromisedOccurrences(0);
            $user->setFailedLoginAttempts(0);
            $user->setRiskScore($securitySnapshot['riskScore']);
            $user->setAccountStatus('ACTIVE');
            $user->setPasswordLastChangedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));

            $em->persist($user);
            $em->flush();
            $legacyUserBridge->syncUser($user);

            $response = $security->login($user, AppAuthenticator::class, 'main');
            if ($response instanceof Response) {
                return $response;
            }

            return $this->redirectToRoute('diet_planner');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'skip_user_context' => true,
        ]);
    }

    #[Route('/register/assistant', name: 'app_register_assistant', methods: ['POST'])]
    public function assistant(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        $firstName = $this->titleCase($this->string($payload, 'firstName'));
        $lastName = $this->titleCase($this->string($payload, 'lastName'));
        $email = mb_strtolower($this->string($payload, 'email'));
        $phone = $this->normalizePhone($this->string($payload, 'phone'));
        $bioDraft = $this->cleanSentence($this->string($payload, 'bio'));
        $role = $this->string($payload, 'role');
        $roleLabel = match ($role) {
            'ROLE_COACH' => 'coach',
            'ROLE_NUTRITIONIST' => 'nutritionist',
            'ROLE_ADMIN' => 'admin',
            default => 'patient',
        };

        $suggestedUsername = '';
        if ($email !== '' && str_contains($email, '@')) {
            $suggestedUsername = (string) strstr($email, '@', true);
        } else {
            $suggestedUsername = strtolower(trim($firstName.'.'.$lastName, '.'));
            $suggestedUsername = preg_replace('/\s+/', '', $suggestedUsername) ?? '';
        }
        if ($suggestedUsername === '') {
            $suggestedUsername = $roleLabel.'_'.substr(preg_replace('/\D+/', '', $phone) ?? '', -4);
            $suggestedUsername = rtrim($suggestedUsername, '_');
        }

        $fullName = trim($firstName.' '.$lastName);
        $identityLead = $fullName !== '' ? $fullName : 'This member';
        $bio = $bioDraft !== ''
            ? sprintf('%s is joining Fitopia as a %s. %s', $identityLead, $roleLabel, $bioDraft)
            : sprintf('%s is joining Fitopia as a %s and is ready to build a healthier routine.', $identityLead, $roleLabel);
        $summary = $fullName !== ''
            ? sprintf(
                '%s profile prepared as %s%s%s.',
                $fullName,
                ucfirst($roleLabel),
                $phone !== '' ? ', phone '.$phone : '',
                $bioDraft !== '' ? ', bio refined' : ''
            )
            : sprintf('Profile ready for a new %s account.', $roleLabel);

        return $this->json([
            'ok' => true,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'username' => $suggestedUsername,
            'phone' => $phone,
            'bio' => $bio,
            'summary' => $summary,
        ]);
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

    private function titleCase(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_replace_callback('/\b[\p{L}\p{M}]+\b/u', static function (array $matches): string {
            $word = mb_strtolower($matches[0]);

            return mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1);
        }, $value) ?? $value;
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/[^\d+]/', '', $value) ?? '';
    }

    private function cleanSentence(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        if ($value === '') {
            return '';
        }

        return rtrim($value, " \t\n\r\0\x0B.").'.';
    }
}

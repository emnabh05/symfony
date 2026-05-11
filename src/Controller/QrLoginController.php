<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\AppAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;

class QrLoginController extends AbstractController
{
    private const TTL_SECONDS = 120;

    #[Route('/login/qr/create', name: 'app_login_qr_create', methods: ['POST'])]
    public function create(Request $request, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        if (!$this->isCsrfTokenValid('qr_login_create', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid QR login token.'], Response::HTTP_BAD_REQUEST);
        }

        $session = $request->getSession();
        $session->start();

        $token = bin2hex(random_bytes(24));
        $expiresAt = new \DateTimeImmutable('+' . self::TTL_SECONDS . ' seconds');
        $record = [
            'token' => $token,
            'browser_session_id' => $session->getId(),
            'status' => 'pending',
            'approved_user_id' => null,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];

        $this->persistRecord($token, $record);

        return $this->json([
            'ok' => true,
            'token' => $token,
            'status' => 'pending',
            'scan_url' => $urlGenerator->generate('app_login_qr_scan', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            'status_url' => $urlGenerator->generate('app_login_qr_status', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            'consume_url' => $urlGenerator->generate('app_login_qr_consume', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ]);
    }

    #[Route('/login/qr/{token}/status', name: 'app_login_qr_status', methods: ['GET'])]
    public function status(string $token, Request $request): JsonResponse
    {
        $record = $this->loadRecord($token);
        if ($record === null) {
            return $this->json(['status' => 'expired'], Response::HTTP_NOT_FOUND);
        }

        $session = $request->getSession();
        $session->start();

        if (($record['browser_session_id'] ?? '') !== $session->getId()) {
            return $this->json(['status' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'status' => (string) ($record['status'] ?? 'expired'),
            'expires_at' => (string) ($record['expires_at'] ?? ''),
        ]);
    }

    #[Route('/login/qr/{token}/consume', name: 'app_login_qr_consume', methods: ['POST'])]
    public function consume(
        string $token,
        Request $request,
        EntityManagerInterface $em,
        Security $security
    ): JsonResponse|RedirectResponse {
        if (!$this->isCsrfTokenValid('qr_login_consume', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid QR consume token.'], Response::HTTP_BAD_REQUEST);
        }

        $record = $this->loadRecord($token);
        if ($record === null) {
            return $this->json(['ok' => false, 'error' => 'QR session expired.'], Response::HTTP_NOT_FOUND);
        }

        $session = $request->getSession();
        $session->start();

        if (($record['browser_session_id'] ?? '') !== $session->getId()) {
            return $this->json(['ok' => false, 'error' => 'Session binding mismatch.'], Response::HTTP_FORBIDDEN);
        }

        $status = (string) ($record['status'] ?? 'expired');
        if ($status !== 'approved') {
            return $this->json(['ok' => false, 'error' => 'QR session not approved yet.', 'status' => $status], Response::HTTP_BAD_REQUEST);
        }

        $approvedUserId = (int) ($record['approved_user_id'] ?? 0);
        /** @var User|null $user */
        $user = $approvedUserId > 0 ? $em->getRepository(User::class)->find($approvedUserId) : null;
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Approved account not found.'], Response::HTTP_NOT_FOUND);
        }

        $record['status'] = 'consumed';
        $record['consumed_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->persistRecord($token, $record);

        $response = $security->login($user, AppAuthenticator::class, 'main');
        $redirectUrl = $this->generateUrl(in_array('ROLE_ADMIN', $user->getRoles(), true) ? 'admin_dashboard' : 'diet_planner');

        if ($response instanceof RedirectResponse) {
            $redirectUrl = $response->getTargetUrl();
        }

        return $this->json([
            'ok' => true,
            'redirect_url' => $redirectUrl,
        ]);
    }

    #[Route('/login/qr/{token}/scan', name: 'app_login_qr_scan', methods: ['GET'])]
    public function scan(string $token): Response
    {
        $record = $this->loadRecord($token);
        $status = $record['status'] ?? 'expired';

        if (!$this->getUser()) {
            $this->addFlash('error', 'Please sign in first to approve this QR login.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/qr_scan.html.twig', [
            'token' => $token,
            'session_status' => $status,
        ]);
    }

    #[Route('/login/qr/{token}/approve', name: 'app_login_qr_approve', methods: ['POST'])]
    public function approve(string $token, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('qr_login_approve', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid QR approval token.');

            return $this->redirectToRoute('app_login_qr_scan', ['token' => $token]);
        }

        $record = $this->loadRecord($token);
        $user = $this->getUser();

        if (!$user instanceof User || $record === null) {
            $this->addFlash('error', 'QR session unavailable.');

            return $this->redirectToRoute('app_login');
        }

        $record['status'] = 'approved';
        $record['approved_user_id'] = $user->getId();
        $record['approved_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->persistRecord($token, $record);

        $this->addFlash('success', 'QR login approved.');

        return $this->redirectToRoute('app_login_qr_scan', ['token' => $token]);
    }

    #[Route('/login/qr/{token}/deny', name: 'app_login_qr_deny', methods: ['POST'])]
    public function deny(string $token, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('qr_login_deny', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid QR deny token.');

            return $this->redirectToRoute('app_login_qr_scan', ['token' => $token]);
        }

        $record = $this->loadRecord($token);
        if ($record !== null) {
            $record['status'] = 'denied';
            $record['denied_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
            $this->persistRecord($token, $record);
        }

        $this->addFlash('success', 'QR login denied.');

        return $this->redirectToRoute('app_login_qr_scan', ['token' => $token]);
    }

    private function loadRecord(string $token): ?array
    {
        $path = $this->recordPath($token);
        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            @unlink($path);

            return null;
        }

        $expiresAt = isset($data['expires_at']) ? new \DateTimeImmutable((string) $data['expires_at']) : null;
        if (!$expiresAt instanceof \DateTimeImmutable || $expiresAt <= new \DateTimeImmutable()) {
            $data['status'] = $data['status'] === 'consumed' ? 'consumed' : 'expired';
            $this->persistRecord($token, $data);
        }

        return $data;
    }

    private function persistRecord(string $token, array $record): void
    {
        $dir = $this->storageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        file_put_contents($this->recordPath($token), json_encode($record, JSON_PRETTY_PRINT));
        $this->cleanupStorage($dir);
    }

    private function cleanupStorage(string $dir): void
    {
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $now = new \DateTimeImmutable();

        foreach ($files as $file) {
            $data = json_decode((string) @file_get_contents($file), true);
            if (!is_array($data)) {
                @unlink($file);
                continue;
            }

            try {
                $expiresAt = new \DateTimeImmutable((string) ($data['expires_at'] ?? ''));
            } catch (\Throwable) {
                @unlink($file);
                continue;
            }

            if ($expiresAt < $now->modify('-10 minutes')) {
                @unlink($file);
            }
        }
    }

    private function storageDir(): string
    {
        $projectDir = $this->getParameter('kernel.project_dir');

        return (string) $projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'qr_login_sessions';
    }

    private function recordPath(string $token): string
    {
        return $this->storageDir() . DIRECTORY_SEPARATOR . preg_replace('/[^a-f0-9]/i', '', $token) . '.json';
    }
}

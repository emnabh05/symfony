<?php

namespace App\Controller;

use App\Entity\QrLoginSession;
use App\Entity\User;
use App\Repository\QrLoginSessionRepository;
use App\Security\AppAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/login/qr')]
class QrLoginController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QrLoginSessionRepository $qrRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/create', name: 'app_login_qr_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse {
        if (!$this->isCsrfTokenValid('qr_login_create', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->checkRateLimit($request, 'qr_create', 8, 60)) {
            return $this->json(['ok' => false, 'error' => 'Too many QR requests. Please wait.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $session = $request->getSession();
        if (!$session instanceof SessionInterface) {
            return $this->json(['ok' => false, 'error' => 'Session unavailable.'], Response::HTTP_BAD_REQUEST);
        }

        $token = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+60 seconds');

        $entry = (new QrLoginSession())
            ->setTokenHash(hash('sha256', $token))
            ->setInitiatorSessionHash(hash('sha256', $session->getId()))
            ->setInitiatorUserAgentHash($this->hashNullable((string) $request->headers->get('User-Agent', '')))
            ->setInitiatorIpHash($this->hashNullable((string) $request->getClientIp()))
            ->setStatus(QrLoginSession::STATUS_PENDING)
            ->setCreatedAt($now)
            ->setExpiresAt($expiresAt);

        $this->em->persist($entry);
        $this->em->flush();

        $this->qrRepository->cleanupOldSessions($now->modify('-1 day'));
        $session->set('qr_login_last_token_hash', $entry->getTokenHash());

        $this->logger->info('QR login session created', [
            'session_hash_prefix' => substr($entry->getInitiatorSessionHash(), 0, 12),
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
        ]);

        return $this->json([
            'ok' => true,
            'scan_url' => $this->generateUrl('app_login_qr_scan', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            'status_url' => $this->generateUrl('app_login_qr_status', ['token' => $token]),
            'consume_url' => $this->generateUrl('app_login_qr_consume', ['token' => $token]),
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
            'ttl_seconds' => 60,
        ]);
    }

    #[Route('/scan/{token}', name: 'app_login_qr_scan', methods: ['GET'])]
    public function scan(string $token, Request $request): Response
    {
        $session = $this->qrRepository->findByRawToken($token);
        if (!$session instanceof QrLoginSession) {
            throw $this->createNotFoundException('QR session not found.');
        }

        $this->expireIfNeeded($session);

        if ($session->getStatus() !== QrLoginSession::STATUS_PENDING) {
            return $this->render('security/qr_scan.html.twig', [
                'session_status' => $session->getStatus(),
                'token' => $token,
            ]);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $request->getSession()->set('_security.main.target_path', $request->getUri());
            $this->addFlash('error', 'Please sign in on this device first to approve QR login.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/qr_scan.html.twig', [
            'session_status' => $session->getStatus(),
            'token' => $token,
        ]);
    }

    #[Route('/approve/{token}', name: 'app_login_qr_approve', methods: ['POST'])]
    public function approve(string $token, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('qr_login_approve', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $request->getSession()->set('_security.main.target_path', $request->getUri());
            $this->addFlash('error', 'Please sign in first.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->checkRateLimit($request, 'qr_approve_'.$user->getId(), 30, 60)) {
            $this->addFlash('error', 'Too many requests. Please try again.');
            return $this->redirectToRoute('app_login_qr_scan', ['token' => $token]);
        }

        $entry = $this->qrRepository->findByRawToken($token);
        if (!$entry instanceof QrLoginSession) {
            throw $this->createNotFoundException('QR session not found.');
        }

        $this->expireIfNeeded($entry);
        if ($entry->getStatus() !== QrLoginSession::STATUS_PENDING) {
            $this->addFlash('error', 'QR session is no longer pending.');
            return $this->redirectToRoute('app_login_qr_scan', ['token' => $token]);
        }

        $entry
            ->setStatus(QrLoginSession::STATUS_APPROVED)
            ->setApprovedAt(new \DateTimeImmutable())
            ->setApprovedByUser($user);
        $this->em->flush();

        $this->logger->info('QR login session approved', [
            'qr_id' => $entry->getId(),
            'approved_by_user_id' => $user->getId(),
        ]);

        $this->addFlash('success', 'QR login approved.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/deny/{token}', name: 'app_login_qr_deny', methods: ['POST'])]
    public function deny(string $token, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('qr_login_deny', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please sign in first.');
            return $this->redirectToRoute('app_login');
        }

        $entry = $this->qrRepository->findByRawToken($token);
        if ($entry instanceof QrLoginSession && $entry->getStatus() === QrLoginSession::STATUS_PENDING) {
            $entry->setStatus(QrLoginSession::STATUS_DENIED);
            $this->em->flush();
            $this->logger->warning('QR login session denied', ['qr_id' => $entry->getId(), 'user_id' => $user->getId()]);
        }

        $this->addFlash('success', 'QR login denied.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/status/{token}', name: 'app_login_qr_status', methods: ['GET'])]
    public function status(
        string $token,
        Request $request
    ): JsonResponse {
        if (!$this->checkRateLimit($request, 'qr_poll', 120, 60)) {
            return $this->json(['status' => 'rate_limited'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $entry = $this->qrRepository->findByRawToken($token);
        if (!$entry instanceof QrLoginSession) {
            return $this->json(['status' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        $this->expireIfNeeded($entry);
        if (!$this->isBoundToInitiator($entry, $request)) {
            return $this->json(['status' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['status' => $entry->getStatus()]);
    }

    #[Route('/consume/{token}', name: 'app_login_qr_consume', methods: ['POST'])]
    public function consume(
        string $token,
        Request $request,
        Security $security
    ): JsonResponse|Response {
        if (!$this->isCsrfTokenValid('qr_login_consume', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
        }

        $entry = $this->qrRepository->findByRawToken($token);
        if (!$entry instanceof QrLoginSession) {
            return $this->json(['ok' => false, 'error' => 'QR session not found.'], Response::HTTP_NOT_FOUND);
        }

        $this->expireIfNeeded($entry);

        if (!$this->isBoundToInitiator($entry, $request)) {
            return $this->json(['ok' => false, 'error' => 'Session binding mismatch.'], Response::HTTP_FORBIDDEN);
        }

        if ($entry->getStatus() !== QrLoginSession::STATUS_APPROVED) {
            return $this->json(['ok' => false, 'error' => 'QR session is not approved.'], Response::HTTP_CONFLICT);
        }

        $user = $entry->getApprovedByUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Approver user is missing.'], Response::HTTP_CONFLICT);
        }

        $entry
            ->setStatus(QrLoginSession::STATUS_CONSUMED)
            ->setConsumedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->logger->info('QR login session consumed', [
            'qr_id' => $entry->getId(),
            'user_id' => $user->getId(),
        ]);

        $loginResponse = $security->login($user, AppAuthenticator::class, 'main');
        if ($loginResponse instanceof Response) {
            return $loginResponse;
        }

        return $this->json([
            'ok' => true,
            'redirect_url' => in_array('ROLE_ADMIN', $user->getRoles(), true)
                ? $this->generateUrl('admin_dashboard')
                : $this->generateUrl('app_profile'),
        ]);
    }

    private function expireIfNeeded(QrLoginSession $session): void
    {
        if (
            $session->getStatus() === QrLoginSession::STATUS_PENDING
            && $session->getExpiresAt() <= new \DateTimeImmutable()
        ) {
            $session->setStatus(QrLoginSession::STATUS_EXPIRED);
            $this->em->flush();
        }
    }

    private function isBoundToInitiator(QrLoginSession $session, Request $request): bool
    {
        $httpSession = $request->getSession();
        if (!$httpSession instanceof SessionInterface) {
            return false;
        }

        $incomingSessionHash = hash('sha256', $httpSession->getId());
        if (!hash_equals($session->getInitiatorSessionHash(), $incomingSessionHash)) {
            return false;
        }

        $uaHash = $this->hashNullable((string) $request->headers->get('User-Agent', ''));
        if ($session->getInitiatorUserAgentHash() !== null && $uaHash !== null && !hash_equals($session->getInitiatorUserAgentHash(), $uaHash)) {
            return false;
        }

        return true;
    }

    private function hashNullable(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : hash('sha256', $trimmed);
    }

    private function rateLimiterKey(Request $request): string
    {
        $sessionId = $request->hasSession() ? (string) $request->getSession()->getId() : '';
        $ip = (string) ($request->getClientIp() ?? 'no-ip');
        return hash('sha256', $sessionId.'|'.$ip);
    }

    private function checkRateLimit(Request $request, string $scope, int $max, int $windowSeconds): bool
    {
        $session = $request->getSession();
        if (!$session instanceof SessionInterface) {
            return false;
        }

        $now = time();
        $sessionKey = '_qr_rate_limit_'.$scope.'_'.$this->rateLimiterKey($request);
        $events = (array) $session->get($sessionKey, []);
        $minTs = $now - $windowSeconds;
        $events = array_values(array_filter($events, static fn ($ts) => is_int($ts) && $ts >= $minTs));

        if (count($events) >= $max) {
            $session->set($sessionKey, $events);
            return false;
        }

        $events[] = $now;
        $session->set($sessionKey, $events);
        return true;
    }
}

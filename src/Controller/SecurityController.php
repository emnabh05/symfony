<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FacePlusPlusCompareService;
use App\Service\SmartAvatarService;
use App\Service\TotpService;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'recaptcha_site_key' => (string) $this->getParameter('app.recaptcha_site_key'),
            'skip_user_context' => true,
        ]);
    }

    #[Route(path: '/face-id/provider-status', name: 'app_face_id_provider_status', methods: ['GET'])]
    public function faceIdProviderStatus(FacePlusPlusCompareService $faceCompare): JsonResponse
    {
        $status = $faceCompare->providerStatus();

        return $this->json($status, $status['ok'] ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    #[Route(path: '/login/avatar', name: 'app_login_avatar', methods: ['GET'])]
    public function loginAvatar(
        Request $request,
        EntityManagerInterface $em,
        SmartAvatarService $smartAvatarService
    ): Response
    {
        $email = trim((string) $request->query->get('email'));
        $resolvedUser = null;

        if ($email !== '') {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user instanceof User && $user->getAvatar()) {
                $avatarPath = $this->getParameter('kernel.project_dir').'/public/uploads/avatars/'.basename((string) $user->getAvatar());
                if ($this->isValidAvatarFile($avatarPath)) {
                    return new BinaryFileResponse($avatarPath);
                }
            }
            if ($user instanceof User) {
                $resolvedUser = $user;
            }
        }

        $fallbackUser = $resolvedUser instanceof User ? $resolvedUser : (new User())
            ->setEmail($email !== '' ? $email : 'guest@fitopia.local')
            ->setUsername($email !== '' ? $email : 'guest')
            ->setFirstName('Guest')
            ->setLastName('User')
            ->setPhone('0000000000')
            ->setBirthDate(new \DateTimeImmutable('2000-01-01'))
            ->setGender('male')
            ->setPassword('placeholder');
        $svg = $smartAvatarService->buildSimpleAvatarSvg($fallbackUser, 160);

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    #[Route(path: '/login/face-id', name: 'app_login_face_id', methods: ['POST'])]
    public function loginFaceId(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        FacePlusPlusCompareService $faceCompare
    ): Response {
        if (!$this->isCsrfTokenValid('face_id_authenticate', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_login');
        }

        $email = trim((string) $request->request->get('email'));
        $faceIdToken = trim((string) $request->request->get('face_id_token'));
        if ($email === '' || $faceIdToken === '') {
            $this->addFlash('error', 'Email and Face ID token are required.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        } catch (DbalException|\PDOException $e) {
            try {
                $connection = $em->getConnection();
                $connection->close();
                $connection->connect();
                $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            } catch (\Throwable) {
                $this->addFlash('error', 'Database is unavailable. Please try again in a moment.');
                return $this->redirectToRoute('app_login');
            }
        }
        if (!$user instanceof User || !$user->isFaceIdEnabled() || $user->getFaceIdTokenHash() === null) {
            $this->addFlash('error', 'Face ID is not configured for this account.');
            return $this->redirectToRoute('app_login');
        }

        $verified = false;
        if (
            $faceCompare->isConfigured()
            && $user->getFaceIdReferenceToken() !== null
            && $user->getFaceIdReferenceToken() !== ''
        ) {
            try {
                $verified = $faceCompare->compareFaceTokens($user->getFaceIdReferenceToken(), $faceIdToken);
            } catch (\RuntimeException) {
                $this->addFlash('error', 'Face ID provider is unavailable. Please use password login.');
                return $this->redirectToRoute('app_login');
            }
        } else {
            $incomingHash = hash('sha256', $faceIdToken);
            $verified = $user->getFaceIdTokenHash() !== null && hash_equals($user->getFaceIdTokenHash(), $incomingHash);
        }

        if (!$verified) {
            $this->addFlash('error', 'Face ID verification failed.');
            return $this->redirectToRoute('app_login');
        }

        $response = $security->login($user, \App\Security\AppAuthenticator::class, 'main');
        if ($response instanceof Response) {
            return $response;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route(path: '/login/face-id/camera', name: 'app_login_face_id_camera', methods: ['POST'])]
    public function loginFaceIdCamera(
        Request $request,
        EntityManagerInterface $em,
        Security $security,
        FacePlusPlusCompareService $faceCompare
    ): Response {
        if (!$this->isCsrfTokenValid('face_id_authenticate', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_login');
        }

        $email = trim((string) $request->request->get('email'));
        $imageBase64 = trim((string) $request->request->get('image_base64'));
        if ($email === '' || $imageBase64 === '') {
            $this->addFlash('error', 'Email and camera capture are required.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        } catch (DbalException|\PDOException) {
            $this->addFlash('error', 'Database is unavailable. Please try again in a moment.');
            return $this->redirectToRoute('app_login');
        }

        if (!$user instanceof User || !$user->isFaceIdEnabled()) {
            $this->addFlash('error', 'Face ID is not configured for this account.');
            return $this->redirectToRoute('app_login');
        }

        $referenceToken = $user->getFaceIdReferenceToken();
        if ($referenceToken === null || $referenceToken === '') {
            $this->addFlash('error', 'Face ID is not enrolled for this account.');
            return $this->redirectToRoute('app_login');
        }

        $provider = $faceCompare->providerStatus();
        if (!$provider['ok']) {
            $this->addFlash('error', 'Face ID provider unavailable: '.(string) ($provider['message'] ?? 'Unknown error').'. Use password login.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $candidateToken = $faceCompare->detectFaceTokenFromBase64($imageBase64);
            $verified = $faceCompare->compareFaceTokens($referenceToken, $candidateToken);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $this->humanizeFaceError($e->getMessage()));
            return $this->redirectToRoute('app_login');
        }

        if (!$verified) {
            $this->addFlash('error', 'Face ID not recognized. This face does not match the enrolled profile.');
            return $this->redirectToRoute('app_login');
        }

        $response = $security->login($user, \App\Security\AppAuthenticator::class, 'main');
        if ($response instanceof Response) {
            return $response;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route(path: '/login/face-id/enroll-camera', name: 'app_login_face_id_enroll_camera', methods: ['POST'])]
    public function enrollFaceIdCamera(): Response
    {
        $this->addFlash('error', 'Face ID enrollment is available from Profile Security only.');
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/2fa/check', name: 'app_2fa_check', methods: ['GET', 'POST'])]
    public function twoFactorCheck(
        Request $request,
        TotpService $totpService,
        Security $security
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();
        $pendingId = (int) $session->get('2fa_pending_user_id', 0);
        if ($pendingId !== (int) $user->getId()) {
            return $this->redirectToRoute('app_profile');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('2fa_check', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('app_2fa_check');
            }

            $attempts = (int) $session->get('2fa_attempts', 0);
            if ($attempts >= 5) {
                $security->logout(false);
                $session->invalidate();
                $this->addFlash('error', 'Too many failed 2FA attempts. Please login again.');
                return $this->redirectToRoute('app_login');
            }

            $code = trim((string) $request->request->get('code'));
            $secret = (string) ($user->getTwoFactorSecret() ?? '');
            if ($secret === '' || !$totpService->verifyCode($secret, $code, 1)) {
                $session->set('2fa_attempts', $attempts + 1);
                $this->addFlash('error', 'Invalid 2FA code.');
                return $this->redirectToRoute('app_2fa_check');
            }

            $session->set('2fa_verified', true);
            $session->remove('2fa_pending_user_id');
            $session->remove('2fa_attempts');

            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $this->redirectToRoute('admin_dashboard');
            }

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('security/2fa_check.html.twig');
    }

    private function isValidAvatarFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $size = @filesize($path);
        if (!is_int($size) || $size <= 0) {
            return false;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $head = (string) @file_get_contents($path, false, null, 0, 512);
            return stripos($head, '<svg') !== false;
        }

        return @getimagesize($path) !== false;
    }

    private function humanizeFaceError(string $message): string
    {
        $m = trim($message);
        if ($m === '') {
            return 'Face ID provider is unavailable. Please use password login.';
        }
        if (stripos($m, 'No face detected') !== false) {
            return 'No face detected. Please look at camera and try again.';
        }
        if (stripos($m, 'CONCURRENCY_LIMIT_EXCEEDED') !== false) {
            return 'Face ID provider is busy. Please retry in a few seconds.';
        }
        if (stripos($m, 'network/SSL') !== false || stripos($m, 'transport unavailable') !== false) {
            return 'Face ID provider is unavailable (network/SSL). Please use password login.';
        }

        return $m;
    }
}

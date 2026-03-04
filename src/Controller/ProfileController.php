<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use App\Service\FacePlusPlusCompareService;
use App\Service\SmartAvatarService;
use App\Service\TotpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile/avatar/view', name: 'app_profile_avatar_view', methods: ['GET'])]
    public function avatarView(SmartAvatarService $smartAvatarService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new Response('', 404);
        }

        $avatar = $user->getAvatar();
        if ($avatar) {
            $path = (string) $this->getParameter('app.avatar_upload_dir').DIRECTORY_SEPARATOR.basename($avatar);
            if ($this->isValidAvatarFile($path)) {
                return new BinaryFileResponse($path);
            }
        }

        $svg = $smartAvatarService->buildSimpleAvatarSvg($user, 256);

        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8']);
    }

    #[Route('/profile', name: 'app_profile')]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        TotpService $totpService
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                if (!$this->isAllowedAvatarExtension($avatarFile)) {
                    $this->addFlash('error', 'Invalid image format. Allowed: jpg, jpeg, png, webp, gif.');
                    return $this->redirectToRoute('app_profile');
                }
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$this->resolveAvatarExtension($avatarFile);

                try {
                    $avatarFile->move(
                        $this->getParameter('app.avatar_upload_dir'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload avatar. Please try again.');
                }

                $user->setAvatar($newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('app_profile');
        }

        $setupSecret = (string) $request->getSession()->get('2fa_setup_secret', '');
        $setupUri = null;
        if ($user instanceof User && $setupSecret !== '') {
            $setupUri = $totpService->getProvisioningUri('Fitopia', $user->getEmail(), $setupSecret);
        }

        return $this->render('profile/edit.html.twig', [
            'profileForm' => $form->createView(),
            'twoFaSetupSecret' => $setupSecret,
            'twoFaSetupUri' => $setupUri,
        ]);
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_profile');
        }

        $em->remove($user);
        $em->flush();

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $this->addFlash('success', 'Your account has been deleted.');
        return $this->redirectToRoute('home');
    }

    #[Route('/profile/avatar/generate', name: 'app_profile_avatar_generate', methods: ['POST'])]
    public function generateAvatar(
        Request $request,
        EntityManagerInterface $em,
        SmartAvatarService $smartAvatarService
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('generate_profile_avatar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_profile');
        }

        $svg = $smartAvatarService->buildStatusAvatarSvg($user);
        $filename = sprintf('smart-avatar-%d-%s.svg', (int) $user->getId(), substr(sha1((string) microtime(true)), 0, 10));
        $targetDir = (string) $this->getParameter('app.avatar_upload_dir');

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $this->addFlash('error', 'Could not prepare avatar directory.');
            return $this->redirectToRoute('app_profile');
        }

        try {
            file_put_contents($targetDir.DIRECTORY_SEPARATOR.$filename, $svg);
        } catch (\Throwable) {
            $this->addFlash('error', 'Could not generate avatar.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setAvatar($filename);
        $em->flush();
        $this->addFlash('success', 'Avatar generated successfully.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/security/face-id/enroll', name: 'app_profile_face_id_enroll', methods: ['POST'])]
    public function enrollFaceId(
        Request $request,
        EntityManagerInterface $em,
        FacePlusPlusCompareService $faceCompare
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_face_id_enroll', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_profile');
        }

        // Allow self-enrollment from profile and enable Face ID automatically.
        if (!$user->isFaceIdEnabled()) {
            $user->setFaceIdEnabled(true);
        }

        $provider = $faceCompare->providerStatus();
        if (!$provider['ok']) {
            $this->addFlash('error', 'Face ID provider unavailable: '.(string) ($provider['message'] ?? 'Unknown error').'.');
            return $this->redirectToRoute('app_profile');
        }

        $imageBase64 = trim((string) $request->request->get('image_base64'));
        if ($imageBase64 === '') {
            $this->addFlash('error', 'Please capture your face first.');
            return $this->redirectToRoute('app_profile');
        }

        try {
            $faceToken = $faceCompare->detectFaceTokenFromBase64($imageBase64);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $this->humanizeFaceError($e->getMessage()));
            return $this->redirectToRoute('app_profile');
        }

        $user->setFaceIdReferenceToken($faceToken);
        $user->setFaceIdTokenHash(hash('sha256', $faceToken));
        $user->setFaceIdUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Face ID enrolled successfully.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/security/face-id/remove', name: 'app_profile_face_id_remove', methods: ['POST'])]
    public function removeFaceId(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_face_id_remove', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setFaceIdReferenceToken(null);
        $user->setFaceIdTokenHash(null);
        $user->setFaceIdUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Face ID removed.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/security/2fa/prepare', name: 'app_profile_2fa_prepare', methods: ['POST'])]
    public function prepareTwoFactor(Request $request, TotpService $totpService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_2fa_prepare', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_profile');
        }

        $request->getSession()->set('2fa_setup_secret', $totpService->generateSecret(32));
        $this->addFlash('success', 'Scan the QR code then enter the 6-digit code to enable 2FA.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/security/2fa/enable', name: 'app_profile_2fa_enable', methods: ['POST'])]
    public function enableTwoFactor(Request $request, EntityManagerInterface $em, TotpService $totpService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_2fa_enable', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_profile');
        }

        $session = $request->getSession();
        $secret = (string) $session->get('2fa_setup_secret', '');
        if ($secret === '') {
            $this->addFlash('error', '2FA setup session expired. Please generate a new QR code.');
            return $this->redirectToRoute('app_profile');
        }

        $code = trim((string) $request->request->get('code'));
        if (!$totpService->verifyCode($secret, $code, 1)) {
            $this->addFlash('error', 'Invalid verification code.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setTwoFactorSecret($secret);
        $user->setTwoFactorEnabled(true);
        $em->flush();

        $session->remove('2fa_setup_secret');
        $this->addFlash('success', '2FA enabled successfully.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/security/2fa/disable', name: 'app_profile_2fa_disable', methods: ['POST'])]
    public function disableTwoFactor(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_2fa_disable', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setTwoFactorEnabled(false);
        $user->setTwoFactorSecret(null);
        $em->flush();

        $session = $request->getSession();
        $session->remove('2fa_setup_secret');
        $session->remove('2fa_pending_user_id');
        $session->set('2fa_verified', true);
        $session->remove('2fa_attempts');

        $this->addFlash('success', '2FA disabled.');
        return $this->redirectToRoute('app_profile');
    }

    private function isAllowedAvatarExtension(UploadedFile $file): bool
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function resolveAvatarExtension(UploadedFile $file): string
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $ext : 'jpg';
    }

    private function generateAvatarFile(string $initials, string $seed): ?string
    {
        [$bg, $fg] = $this->buildAvatarColors($seed);
        $svg = $this->buildAvatarSvg($initials, $bg, $fg);
        $targetDir = (string) $this->getParameter('app.avatar_upload_dir');
        $filename = sprintf('avatar-%s.svg', substr(sha1($seed.microtime(true)), 0, 16));

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return null;
        }

        try {
            file_put_contents($targetDir.DIRECTORY_SEPARATOR.$filename, $svg);
            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildAvatarInitials(string $firstName, string $lastName, string $username): string
    {
        $a = strtoupper(substr(trim($firstName), 0, 1));
        $b = strtoupper(substr(trim($lastName), 0, 1));
        $initials = trim($a.$b);
        if ($initials === '') {
            $initials = strtoupper(substr(trim($username), 0, 2));
        }

        return $initials !== '' ? $initials : 'U';
    }

    private function buildAvatarColors(string $seed): array
    {
        $palette = [
            ['#0B3A3D', '#E6FFFA'],
            ['#164E63', '#ECFEFF'],
            ['#7C2D12', '#FFF7ED'],
            ['#365314', '#F7FEE7'],
            ['#9A3412', '#FFEDD5'],
        ];
        $index = abs(crc32($seed !== '' ? $seed : 'fitopia')) % count($palette);

        return $palette[$index];
    }

    private function buildAvatarSvg(string $initials, string $bg, string $fg): string
    {
        $safeInitials = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <rect width="256" height="256" rx="128" fill="{$bg}"/>
  <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle"
        font-family="Poppins, Arial, sans-serif" font-size="96" font-weight="700" fill="{$fg}">{$safeInitials}</text>
</svg>
SVG;
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
            return 'Face ID provider is unavailable. Please try again later.';
        }
        if (stripos($m, 'No face detected') !== false) {
            return 'No face detected. Please look at the camera with good lighting and retry.';
        }
        if (stripos($m, 'CONCURRENCY_LIMIT_EXCEEDED') !== false) {
            return 'Face ID provider is busy. Please retry in a few seconds.';
        }
        if (stripos($m, 'network/SSL') !== false || stripos($m, 'transport unavailable') !== false) {
            return 'Face ID provider is unavailable (network/SSL). Please try again later.';
        }

        return $m;
    }
}

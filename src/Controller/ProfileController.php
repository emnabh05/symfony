<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FacePlusPlusCompareService;
use App\Service\LegacyUserBridgeService;
use App\Service\SmartAvatarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
            $avatarDir = $this->getParameter('app.avatar_upload_dir');
            if (!is_string($avatarDir)) {
                return new Response('', 404);
            }
            $path = $avatarDir.DIRECTORY_SEPARATOR.basename($avatar);
            if ($this->isValidAvatarFile($path)) {
                return new BinaryFileResponse($path);
            }
        }

        $svg = $smartAvatarService->buildSimpleAvatarSvg($user, 256);

        return new Response($svg, 200, ['Content-Type' => 'image/svg+xml; charset=UTF-8']);
    }

    #[Route('/profile', name: 'app_profile')]
    public function edit(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/profile/face-id/enroll', name: 'app_profile_face_id_enroll', methods: ['POST'])]
    public function enrollFaceId(
        Request $request,
        EntityManagerInterface $em,
        FacePlusPlusCompareService $faceCompare,
        LegacyUserBridgeService $legacyUserBridge
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_face_id_enroll', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('diet_planner');
        }

        $imageBase64 = trim((string) $request->request->get('image_base64'));
        if ($imageBase64 === '') {
            $this->addFlash('error', 'Face capture is required.');
            return $this->redirectToRoute('diet_planner');
        }

        try {
            $referenceToken = $faceCompare->detectFaceTokenFromBase64($imageBase64);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('diet_planner');
        }

        $user
            ->setFaceIdEnabled(true)
            ->setFaceImagePath('face-token:'.$referenceToken)
            ->setFaceIdReferenceToken($referenceToken)
            ->setFaceIdTokenHash(hash('sha256', $referenceToken));

        $em->flush();
        $legacyUserBridge->syncUser($user);
        $this->addFlash('success', 'Face ID saved successfully.');

        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/profile/face-id/remove', name: 'app_profile_face_id_remove', methods: ['POST'])]
    public function removeFaceId(
        Request $request,
        EntityManagerInterface $em,
        LegacyUserBridgeService $legacyUserBridge
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('profile_face_id_remove', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('diet_planner');
        }

        $user
            ->setFaceIdEnabled(false)
            ->setFaceImagePath(null)
            ->setFaceIdReferenceToken(null)
            ->setFaceIdTokenHash(null);

        $em->flush();
        $legacyUserBridge->syncUser($user);
        $this->addFlash('success', 'Face ID removed successfully.');

        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        LegacyUserBridgeService $legacyUserBridge
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('diet_planner');
        }

        $legacyUserBridge->deleteUser($user);
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
        SmartAvatarService $smartAvatarService,
        LegacyUserBridgeService $legacyUserBridge
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('generate_profile_avatar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('diet_planner');
        }

        $svg = $smartAvatarService->buildStatusAvatarSvg($user);
        $filename = sprintf('smart-avatar-%d-%s.svg', (int) $user->getId(), substr(sha1((string) microtime(true)), 0, 10));
        $targetDir = $this->getParameter('app.avatar_upload_dir');
        if (!is_string($targetDir)) {
            $this->addFlash('error', 'Invalid avatar directory.');
            return $this->redirectToRoute('diet_planner');
        }

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $this->addFlash('error', 'Could not prepare avatar directory.');
            return $this->redirectToRoute('diet_planner');
        }

        try {
            file_put_contents($targetDir.DIRECTORY_SEPARATOR.$filename, $svg);
        } catch (\Throwable) {
            $this->addFlash('error', 'Could not generate avatar.');
            return $this->redirectToRoute('diet_planner');
        }

        $user->setAvatar($filename);
        $em->flush();
        $legacyUserBridge->syncUser($user);
        $this->addFlash('success', 'Avatar generated successfully.');

        return $this->redirectToRoute('diet_planner');
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
}

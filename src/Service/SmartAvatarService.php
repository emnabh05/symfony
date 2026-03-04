<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SmartAvatarService
{
    public function __construct(
        private readonly FacePlusPlusCompareService $faceService
    ) {
    }

    public function resolveRoleBadge(User $user): array
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return ['key' => 'admin', 'label' => 'Admin', 'icon' => 'shield', 'color' => '#0B3A3D'];
        }
        if (in_array('ROLE_COACH', $roles, true)) {
            return ['key' => 'coach', 'label' => 'Coach', 'icon' => 'bolt', 'color' => '#164E63'];
        }
        if (in_array('ROLE_NUTRITIONIST', $roles, true)) {
            return ['key' => 'nutritionist', 'label' => 'Nutritionist', 'icon' => 'leaf', 'color' => '#365314'];
        }

        return ['key' => 'user', 'label' => 'User', 'icon' => 'user', 'color' => '#7C2D12'];
    }

    public function resolveUserStatus(User $user): array
    {
        if ($user->isFaceIdEnabled() && $user->getFaceIdUpdatedAt() !== null) {
            return ['key' => 'verified', 'label' => 'Verified', 'accent' => '#0f7a5f'];
        }
        if ($user->isPasswordMustChange()) {
            return ['key' => 'action_required', 'label' => 'Action Required', 'accent' => '#b42318'];
        }

        return ['key' => 'standard', 'label' => 'Standard', 'accent' => '#1f3f68'];
    }

    public function suggestProfessionalPhoto(User $user): array
    {
        $badge = $this->resolveRoleBadge($user);
        $status = $this->resolveUserStatus($user);

        $tips = [
            'Use neutral background and frontal face framing.',
            'Keep lighting soft and uniform, avoid strong backlight.',
            'Avoid sunglasses/caps; keep full face visible.',
            'Use 1:1 crop with head-and-shoulders composition.',
        ];

        if ($badge['key'] === 'admin') {
            $tips[] = 'Prefer formal outfit and high-contrast clean portrait.';
        } elseif ($badge['key'] === 'coach') {
            $tips[] = 'Prefer energetic but professional sport outfit.';
        } elseif ($badge['key'] === 'nutritionist') {
            $tips[] = 'Prefer clean clinical/professional visual style.';
        }

        return [
            'persona' => $badge['label'].' '.$status['label'],
            'headline' => 'Professional avatar suggestion',
            'tips' => $tips,
        ];
    }

    public function analyzeImageQuality(?UploadedFile $file, User $user): array
    {
        if (!$file instanceof UploadedFile) {
            return [
                'ok' => false,
                'score' => 0,
                'issues' => ['No image uploaded.'],
                'face' => ['detected' => false, 'provider' => 'none'],
            ];
        }

        $issues = [];
        $score = 100;
        $mime = (string) $file->getMimeType();
        $size = (int) ($file->getSize() ?? 0);
        $path = $file->getPathname();
        [$width, $height] = @getimagesize($path) ?: [0, 0];

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $issues[] = 'Unsupported format. Use JPEG/PNG/WEBP.';
            $score -= 25;
        }
        if ($size > 2 * 1024 * 1024) {
            $issues[] = 'Image is too heavy (>2MB).';
            $score -= 20;
        }
        if ($width < 320 || $height < 320) {
            $issues[] = 'Resolution too low (minimum 320x320).';
            $score -= 20;
        }
        if ($width > 0 && $height > 0) {
            $ratio = $width / max(1, $height);
            if ($ratio < 0.75 || $ratio > 1.35) {
                $issues[] = 'Aspect ratio should be close to portrait/square.';
                $score -= 10;
            }
        }

        $face = $this->detectFace($file, $user);
        if (!$face['detected']) {
            $issues[] = 'No clear face detected.';
            $score -= 25;
        }

        $score = max(0, min(100, $score));

        return [
            'ok' => $score >= 65,
            'score' => $score,
            'meta' => [
                'mime' => $mime,
                'size_bytes' => $size,
                'width' => $width,
                'height' => $height,
            ],
            'issues' => $issues,
            'face' => $face,
        ];
    }

    public function buildStatusAvatarSvg(User $user): string
    {
        $status = $this->resolveUserStatus($user);
        $badge = $this->resolveRoleBadge($user);
        $initials = $this->initials($user);
        $bg = $status['accent'];
        $fg = '#ffffff';
        $safeInitials = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
        $badgeChar = strtoupper(substr($badge['label'], 0, 1));

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$bg}" />
      <stop offset="100%" stop-color="#0B3A3D" />
    </linearGradient>
  </defs>
  <rect width="256" height="256" rx="128" fill="url(#g)"/>
  <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle"
        font-family="Poppins, Arial, sans-serif" font-size="92" font-weight="700" fill="{$fg}">{$safeInitials}</text>
  <circle cx="206" cy="50" r="26" fill="#ffffff" fill-opacity="0.92"/>
  <text x="206" y="54" text-anchor="middle" dominant-baseline="middle"
        font-family="Poppins, Arial, sans-serif" font-size="22" font-weight="800" fill="#0B3A3D">{$badgeChar}</text>
</svg>
SVG;
    }

    private function detectFace(UploadedFile $file, User $user): array
    {
        if ($this->faceService->isConfigured() && $this->faceService->isTransportAvailable()) {
            try {
                $raw = (string) @file_get_contents($file->getPathname());
                $token = $this->faceService->detectFaceTokenFromBase64(base64_encode($raw));

                return ['detected' => $token !== '', 'provider' => 'face++'];
            } catch (\Throwable) {
                return ['detected' => true, 'provider' => 'local_fallback'];
            }
        }

        [$w, $h] = @getimagesize($file->getPathname()) ?: [0, 0];
        $likely = $w >= 320 && $h >= 320;

        return ['detected' => $likely, 'provider' => 'local_heuristic'];
    }

    private function initials(User $user): string
    {
        $a = strtoupper(substr(trim((string) $user->getFirstName()), 0, 1));
        $b = strtoupper(substr(trim((string) $user->getLastName()), 0, 1));
        $initials = trim($a.$b);
        if ($initials === '') {
            $initials = strtoupper(substr(trim((string) $user->getUsername()), 0, 2));
        }

        return $initials !== '' ? $initials : 'U';
    }
}

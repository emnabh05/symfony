<?php

namespace App\Service;

use App\Entity\User;

class SmartAvatarService
{
    public function buildSimpleAvatarSvg(User $user, int $size = 160): string
    {
        $initials = $this->initials($user);
        $palette = $this->palette($user);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%%" stop-color="%2$s"/><stop offset="100%%" stop-color="%3$s"/></linearGradient></defs><rect width="%1$d" height="%1$d" rx="%4$d" fill="url(#g)"/><text x="50%%" y="54%%" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="%5$d" font-weight="700" fill="#ffffff">%6$s</text></svg>',
            $size,
            $palette[0],
            $palette[1],
            (int) round($size * 0.22),
            max(22, (int) round($size * 0.28)),
            htmlspecialchars($initials, ENT_QUOTES)
        );
    }

    public function buildStatusAvatarSvg(User $user): string
    {
        return $this->buildSimpleAvatarSvg($user, 256);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function palette(User $user): array
    {
        $seed = crc32(strtolower($user->getEmail() ?: $user->getUsername() ?: 'fitopia'));
        $palettes = [
            ['#0b4a56', '#13866a'],
            ['#124e78', '#3aa6b9'],
            ['#9a3412', '#ea580c'],
            ['#1d4ed8', '#22c55e'],
        ];

        return $palettes[$seed % count($palettes)];
    }

    private function initials(User $user): string
    {
        $parts = array_filter([
            $user->getFirstName(),
            $user->getLastName(),
        ]);
        if ($parts === []) {
            $source = $user->getUsername() !== '' ? $user->getUsername() : $user->getEmail();
            $source = preg_replace('/[^a-z0-9]+/i', ' ', (string) $source);
            $parts = array_filter(explode(' ', trim((string) $source)));
        }

        $initials = '';
        foreach (array_slice(array_values($parts), 0, 2) as $part) {
            $initials .= strtoupper(substr((string) $part, 0, 1));
        }

        return $initials !== '' ? $initials : 'F';
    }
}

<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class EventParticipantEmailResolver
{
    private const SESSION_KEY = 'events_participant_email';

    public function resolve(Request $request, mixed $user = null): ?string
    {
        if (\is_object($user) && \method_exists($user, 'getEmail')) {
            $userEmail = $this->sanitizeEmail((string) $user->getEmail());
            if ($userEmail !== null) {
                $this->rememberEmail($request, $userEmail);
                return $userEmail;
            }
        }

        if (!$request->hasSession()) {
            return null;
        }

        return $this->sanitizeEmail((string) $request->getSession()->get(self::SESSION_KEY, ''));
    }

    public function rememberEmail(Request $request, string $email): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $normalized = $this->sanitizeEmail($email);
        if ($normalized !== null) {
            $request->getSession()->set(self::SESSION_KEY, $normalized);
        }
    }

    private function sanitizeEmail(string $email): ?string
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }
}

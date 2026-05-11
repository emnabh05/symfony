<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserPasswordCompatibilityService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
        private readonly LegacyUserBridgeService $legacyUserBridge
    ) {
    }

    public function verifyAndUpgrade(User $user, string $plainPassword): bool
    {
        if ($this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            return true;
        }

        $storedPassword = (string) $user->getPassword();
        if ($storedPassword === '') {
            return false;
        }

        if (password_verify($plainPassword, $storedPassword)) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        if (hash_equals($storedPassword, $plainPassword)) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        $legacyCrypt = @crypt($plainPassword, $storedPassword);
        if (is_string($legacyCrypt) && $legacyCrypt !== '' && hash_equals($storedPassword, $legacyCrypt)) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        if (preg_match('/^[a-f0-9]{32}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), md5($plainPassword))) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        if (preg_match('/^[a-f0-9]{40}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), sha1($plainPassword))) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), hash('sha256', $plainPassword))) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        if (preg_match('/^[a-f0-9]{128}$/i', $storedPassword) && hash_equals(strtolower($storedPassword), hash('sha512', $plainPassword))) {
            $this->upgradePassword($user, $plainPassword);

            return true;
        }

        return false;
    }

    private function upgradePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPasswordLastChangedAt((new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        $this->em->flush();
        $this->legacyUserBridge->syncUser($user);
    }
}

<?php

namespace App\Tests\Service;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    public function testUserRolesAlwaysContainRoleUser(): void
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setUsername('user')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPhone('0000000000')
            ->setBirthDate(new \DateTimeImmutable('1999-01-01'))
            ->setGender('male')
            ->setPassword('hashed')
            ->setRoles(['ROLE_PATIENT']);

        self::assertContains('ROLE_PATIENT', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testRiskScoreIsClampedBetweenZeroAndHundred(): void
    {
        $user = new User();
        $user->setRiskScore(-10);
        self::assertSame(0, $user->getRiskScore());

        $user->setRiskScore(150);
        self::assertSame(100, $user->getRiskScore());
    }

    public function testTwoFactorCanBeEnabledAndSecretStored(): void
    {
        $user = new User();
        $user->setTwoFactorEnabled(true)->setTwoFactorSecret('ABCDEF123456');

        self::assertTrue($user->isTwoFactorEnabled());
        self::assertSame('ABCDEF123456', $user->getTwoFactorSecret());
    }
}

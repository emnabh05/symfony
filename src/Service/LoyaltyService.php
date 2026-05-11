<?php

namespace App\Service;

use App\Repository\ReservationRepository;

class LoyaltyService
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository
    ) {
    }

    public function getVipReservationThreshold(): int
    {
        $value = $_ENV['FITOPIA_VIP_RESERVATION_THRESHOLD'] ?? $_SERVER['FITOPIA_VIP_RESERVATION_THRESHOLD'] ?? '3';

        return max(1, (int) $value);
    }

    public function isVipEmail(string $email): bool
    {
        $email = trim($email);
        if ($email === '') {
            return false;
        }

        try {
            return $this->reservationRepository->countByEmail($email) >= $this->getVipReservationThreshold();
        } catch (\Throwable) {
            return false;
        }
    }
}

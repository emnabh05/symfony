<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReservationQrService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildValidationUrl(Reservation $reservation): string
    {
        return $this->urlGenerator->generate(
            'app_reservation_qr_validate',
            ['token' => $reservation->getQrToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function buildQrImageUrl(string $validationUrl, int $size = 320): string
    {
        $safeSize = max(120, min(1000, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?size='
            .$safeSize.'x'.$safeSize
            .'&data='.rawurlencode($validationUrl);
    }
}

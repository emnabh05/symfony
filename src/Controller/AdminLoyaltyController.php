<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\LoyaltyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class AdminLoyaltyController extends AbstractController
{
    #[Route('/loyalty', name: 'loyalty', methods: ['GET'])]
    public function index(
        Request $request,
        ReservationRepository $reservationRepository,
        LoyaltyService $loyaltyService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $filter = mb_strtolower(trim((string) $request->query->get('filter', 'all')));
        if (!in_array($filter, ['all', 'vip', 'standard'], true)) {
            $filter = 'all';
        }

        $vipThreshold = $loyaltyService->getVipReservationThreshold();
        $clients = $reservationRepository->getClientLoyaltyOverview($vipThreshold, $filter);
        $totalVip = 0;
        foreach ($clients as $client) {
            if (($client['loyaltyStatus'] ?? '') === 'VIP') {
                $totalVip++;
            }
        }

        return $this->render('admin/loyalty/index.html.twig', [
            'clients' => $clients,
            'filter' => $filter,
            'vipThreshold' => $vipThreshold,
            'totalVip' => $totalVip,
            'totalClients' => count($clients),
        ]);
    }
}

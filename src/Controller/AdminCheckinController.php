<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/checkin', name: 'admin_checkin_')]
class AdminCheckinController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ReservationRepository $reservationRepository
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_checkin_search', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de securite invalide.');
                return $this->redirectToRoute('admin_checkin_index');
            }

            $token = trim((string) $request->request->get('token'));
            if ($token === '') {
                $this->addFlash('error', 'Veuillez saisir un token QR.');
                return $this->redirectToRoute('admin_checkin_index');
            }

            return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
        }

        $query = trim((string) $request->query->get('q', ''));
        $isSearchActive = $query !== '';
        $recentReservations = $isSearchActive
            ? $reservationRepository->searchForCheckin($query)
            : $reservationRepository->listForAdmin(20);
        return $this->render('admin/checkin/index.html.twig', [
            'recentReservations' => $recentReservations,
            'isSearchActive' => $isSearchActive,
            'searchQuery' => $query,
        ]);
    }

    #[Route('/{token}', name: 'show', methods: ['GET'])]
    public function show(string $token, ReservationRepository $reservationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/checkin/show.html.twig', [
            'reservation' => $reservationRepository->findByQrToken($token),
            'token' => $token,
        ]);
    }

    #[Route('/{token}/validate', name: 'validate', methods: ['POST'])]
    public function validateEntry(
        string $token,
        Request $request,
        ReservationRepository $reservationRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('admin_checkin_validate_'.$token, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
        }

        $reservation = $reservationRepository->findByQrToken($token);
        if (!$reservation instanceof Reservation) {
            $this->addFlash('error', 'QR invalide.');
            return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
        }

        if ($reservation->getStatut() === Reservation::STATUS_CANCELLED) {
            $this->addFlash('error', 'Reservation annulee');
            return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
        }

        if ($reservation->isUsed()) {
            $this->addFlash('info', 'Deja validee');
            return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
        }

        if ($reservation->getStatut() !== Reservation::STATUS_CONFIRMED) {
            $this->addFlash('error', 'QR invalide.');
            return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
        }

        $now = new \DateTimeImmutable();
        $reservation->setStatut(Reservation::STATUS_USED);
        $reservation->setCheckedInAt($now);
        $reservation->setUsedAt($now);
        $em->flush();

        $this->addFlash('success', 'Entree validee');
        return $this->redirectToRoute('admin_checkin_show', ['token' => $token]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventReview;
use App\Entity\Participation;
use App\Entity\Reservation;
use App\Form\EventReviewType;
use App\Repository\EventReviewRepository;
use App\Service\EventParticipantEmailResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Attribute\Route;

class ReviewController extends AbstractController
{
    #[Route('/events/{id}/review', name: 'app_event_review_upsert', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function upsert(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        EventReviewRepository $eventReviewRepository,
        EventParticipantEmailResolver $emailResolver
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('event_review_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');
            return $this->redirectBack($request, $id);
        }

        $email = $emailResolver->resolve($request, $this->getUser());
        if ($email === null) {
            $this->addFlash('error', 'Aucun email participant detecte. Veuillez vous connecter ou reserver.');
            return $this->redirectBack($request, $id);
        }

        $event = $em->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            $this->addFlash('error', 'Evenement introuvable.');
            return $this->redirectToRoute('events');
        }

        if (!$this->hasParticipated($em, $id, $email)) {
            $this->addFlash('error', 'Vous devez participer à cet événement pour laisser un avis.');
            return $this->redirectBack($request, $id);
        }

        $review = $eventReviewRepository->findOneByEmailAndEvent($email, $id);
        if (!$review instanceof EventReview) {
            $review = new EventReview();
            $review->setEvent($event);
            $review->setEmailParticipant($email);
            $review->setCreatedAt(new \DateTimeImmutable());
        } else {
            $review->setUpdatedAt(new \DateTimeImmutable());
        }

        $form = $this->createForm(EventReviewType::class, $review);
        $form->submit([
            'note' => $request->request->get('note'),
            'commentaire' => $request->request->get('commentaire'),
        ]);
        if (!$form->isValid()) {
            foreach ($this->collectFormErrors($form) as $errorMessage) {
                $this->addFlash('error', $errorMessage);
            }
            $this->addFlash('error', 'Veuillez corriger les erreurs du formulaire.');

            return $this->redirectBack($request, $id);
        }

        $em->persist($review);
        $em->flush();

        $this->addFlash('success', 'Votre avis a ete enregistre.');
        return $this->redirectBack($request, $id);
    }

    private function hasParticipated(EntityManagerInterface $em, int $eventId, string $email): bool
    {
        $email = mb_strtolower(trim($email));

        $participationCount = (int) $em->getRepository(Participation::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('IDENTITY(p.event) = :eventId')
            ->andWhere('LOWER(p.emailParticipant) = :email')
            ->setParameter('eventId', $eventId)
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
        if ($participationCount > 0) {
            return true;
        }

        $reservationCount = (int) $em->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('IDENTITY(r.event) = :eventId')
            ->andWhere('LOWER(r.emailParticipant) = :email')
            ->andWhere('r.statut IN (:allowed)')
            ->setParameter('eventId', $eventId)
            ->setParameter('email', $email)
            ->setParameter('allowed', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_USED])
            ->getQuery()
            ->getSingleScalarResult();

        return $reservationCount > 0;
    }

    private function redirectBack(Request $request, int $eventId): RedirectResponse
    {
        $url = trim((string) $request->request->get('redirect_url', ''));
        if ($url !== '') {
            return $this->redirect($url);
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('events', ['event' => $eventId]);
    }

    /**
     * @return string[]
     */
    private function collectFormErrors(FormInterface $form): array
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $message = trim($error->getMessage());
            if ($message !== '') {
                $messages[] = $message;
            }
        }

        return array_values(array_unique($messages));
    }
}

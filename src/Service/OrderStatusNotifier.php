<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class OrderStatusNotifier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
    ) {
    }

    /**
     * @return array{created: bool, emailSent: bool, reason: string|null}
     */
    public function notifyStatusChange(Order $order, string $oldStatus, string $newStatus): array
    {
        $recipient = $order->getEmail();
        if (!$recipient) {
            return [
                'created' => false,
                'emailSent' => false,
                'reason' => 'missing_email',
            ];
        }

        $orderNumber = $order->getOrderNumber() ?? 'Unknown';
        $oldLabel = $this->formatStatus($oldStatus);
        $newLabel = $this->formatStatus($newStatus);

        $message = sprintf(
            'Order %s status changed from %s to %s.',
            $orderNumber,
            $oldLabel,
            $newLabel
        );

        $notification = new Notification();
        $notification->setEmail($recipient);
        $notification->setOrderNumber($orderNumber);
        $notification->setStatus($newStatus);
        $notification->setMessage($message);
        $notification->setOrderRef($order);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $emailSent = false;
        $reason = null;
        if ($this->shouldSendDeliveryEmail($newStatus)) {
            $emailSent = $this->sendDeliveryEmail($order);
            if (!$emailSent) {
                $reason = 'delivery_email_failed';
            }
        } else {
            $reason = 'status_not_delivered';
        }

        return [
            'created' => true,
            'emailSent' => $emailSent,
            'reason' => $reason,
        ];
    }

    private function shouldSendDeliveryEmail(string $newStatus): bool
    {
        return strtolower(trim($newStatus)) === 'delivered';
    }

    private function sendDeliveryEmail(Order $order): bool
    {
        $recipient = trim((string) $order->getEmail());
        if ($recipient === '') {
            return false;
        }

        $fromAddress = $_ENV['MAILER_FROM'] ?? 'no-reply@fitopia.local';
        $fromName = $_ENV['MAILER_FROM_NAME'] ?? 'Fitopia Supplements';
        $orderNumber = $order->getOrderNumber() ?? 'Unknown';
        $recipientName = trim((string) $order->getFirstName() . ' ' . (string) $order->getLastName());

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($fromAddress, $fromName))
                ->to(new Address($recipient, $recipientName))
                ->subject(sprintf('Order %s delivered', $orderNumber))
                ->htmlTemplate('emails/order_delivered.html.twig')
                ->textTemplate('emails/order_delivered.txt.twig')
                ->context([
                    'order' => $order,
                ]);

            $this->mailer->send($email);
            return true;
        } catch (\Throwable $e) {
            error_log(sprintf(
                'Delivered email failed for order %s (%s): %s',
                $orderNumber,
                $recipient,
                $e->getMessage()
            ));
            return false;
        }
    }

    private function formatStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'Unknown';
        }

        return match ($status) {
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'processing' => 'Processing',
            'on_way' => 'On the way',
            'delivered' => 'Delivered',
            'canceled', 'cancelled' => 'Canceled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

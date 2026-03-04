<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\OrderStatusNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/order')]
class OrderController extends AbstractController
{
    public function __construct(
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private OrderStatusNotifier $orderStatusNotifier,
    ) {
    }

    #[Route('', name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $orders = $this->orderRepository->findByStatus($status);
        } else {
            $orders = $this->orderRepository->findBy([], ['createdAt' => 'DESC']);
        }
        
        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/status', name: 'app_order_update_status', methods: ['POST'])]
    public function updateStatus(Request $request, Order $order): Response
    {
        $newStatus = $request->request->get('status');

        if (!$newStatus) {
            $this->addFlash('error', 'No status was provided.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $allowedStatuses = ['pending', 'accepted', 'delivered', 'canceled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $this->addFlash('error', 'Invalid status selected.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $oldStatus = $order->getStatus();
        if ($oldStatus === $newStatus) {
            $this->addFlash('info', 'Order status is already set to this value.');
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }

        $order->setStatus($newStatus);
        $this->entityManager->flush();

        $notifyResult = $this->orderStatusNotifier->notifyStatusChange($order, (string) $oldStatus, $newStatus);
        if ($notifyResult['created']) {
            if ($newStatus === 'delivered') {
                if ($notifyResult['emailSent']) {
                    $this->addFlash('success', 'Order delivered. Customer delivery email was sent.');
                } else {
                    $this->addFlash('warning', 'Order delivered, but delivery email could not be sent.');
                }
            } elseif ($notifyResult['emailSent']) {
                $this->addFlash('success', 'Order status updated. Notification saved and email sent.');
            } else {
                $this->addFlash('success', 'Order status updated. Notification saved to the customer center.');
            }
        } else {
            $this->addFlash('info', 'Order status updated, but notification was not created (missing email).');
        }
        
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order): Response
    {
        $this->entityManager->remove($order);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Order deleted successfully!');
        
        return $this->redirectToRoute('app_order_index');
    }
}

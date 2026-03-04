<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Review;
use App\Entity\Supplement;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\SupplementRepository;
use App\Service\AiProductSuggestionService;
use App\Service\MonthlyLeaderboardService;
use App\Service\SmartSubstituteEngine;
use App\Service\SupplementProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shop')]
class ShopController extends AbstractController
{
    public function __construct(
        private SupplementRepository $supplementRepository,
        private EntityManagerInterface $entityManager,
        private SmartSubstituteEngine $smartSubstituteEngine,
        private AiProductSuggestionService $aiProductSuggestionService,
        #[Autowire('%env(string:MAILER_DSN)%')]
        private readonly string $mailerDsn = 'null://null',
    ) {
    }

    #[Route('', name: 'app_shop_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get all supplements
        $supplements = $this->supplementRepository->findCatalogLimited();
        $supplementIds = array_map(static fn($supplement) => $supplement->getId(), $supplements);
        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $ratingSummary = method_exists($reviewRepository, 'getSummaryForSupplements')
            ? $reviewRepository->getSummaryForSupplements($supplementIds)
            : [];
        
        // Get unique categories and brands
        $categories = array_unique(array_map(fn($s) => $s->getCategory(), $supplements));
        $brands = array_unique(array_map(fn($s) => $s->getBrand(), $supplements));
        
        sort($categories);
        sort($brands);

        $paymentMethods = [
            ['id' => 'visa', 'name' => 'Visa', 'icon' => 'fab fa-cc-visa'],
            ['id' => 'mastercard', 'name' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard'],
            ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'fab fa-cc-paypal'],
            ['id' => 'cod', 'name' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave'],
        ];
        
        return $this->render('pages/supplements.html.twig', [
            'mode' => 'shop',
            'supplements' => $supplements,
            'categories' => $categories,
            'brands' => $brands,
            'ratingSummary' => $ratingSummary,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    #[Route('/cart', name: 'app_shop_cart', methods: ['GET'])]
    public function cart(SessionInterface $session): Response
    {
        // Ensure session is started
        if (!$session->isStarted()) {
            $session->start();
        }

        $cart = $session->get('cart', []);
        $cartItems = [];
        $subtotal = 0;

        // Debug: Log what's in the session
        error_log("=== CART PAGE DEBUG ===");
        error_log("Session ID: " . $session->getId());
        error_log("Cart from session: " . json_encode($cart));
        error_log("Cart count: " . count($cart));

        foreach ($cart as $item) {
            $supplement = $this->supplementRepository->find($item['id']);
            if ($supplement) {
                $itemTotal = $supplement->getPrice() * $item['quantity'];
                $cartItems[] = [
                    'supplement' => $supplement,
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal,
                ];
                $subtotal += $itemTotal;
            }
        }

        error_log("Cart items count: " . count($cartItems));
        error_log("======================");

        return $this->render('pages/supplements.html.twig', [
            'mode' => 'cart',
            'supplements' => $this->supplementRepository->findCatalogLimited(),
            'categories' => [],
            'brands' => [],
            'ratingSummary' => [],
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
        ]);
    }

    #[Route('/cart/add', name: 'app_shop_cart_add', methods: ['POST'])]
    public function addToCart(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$session->isStarted()) {
            $session->start();
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $supplementId = $data['supplementId'] ?? null;
        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        if (!$supplementId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid supplement ID'], 400);
        }

        $supplement = $this->supplementRepository->find($supplementId);
        if (!$supplement) {
            return new JsonResponse(['success' => false, 'message' => 'Supplement not found'], 404);
        }

        $cart = $session->get('cart', []);
        $existingQuantity = 0;
        foreach ($cart as $item) {
            if ((int) ($item['id'] ?? 0) === (int) $supplementId) {
                $existingQuantity = (int) ($item['quantity'] ?? 0);
                break;
            }
        }

        $requestedTotal = $existingQuantity + $quantity;
        $availableStock = (int) ($supplement->getStock() ?? 0);
        if ($requestedTotal > $availableStock) {
            $substitutes = $this->smartSubstituteEngine->suggestForSupplement($supplement, $quantity, 4);

            return new JsonResponse([
                'success' => false,
                'message' => sprintf(
                    'Only %d unit(s) available for "%s".',
                    $availableStock,
                    (string) $supplement->getName()
                ),
                'substitutes' => $this->formatSubstituteSuggestions($substitutes),
            ], 409);
        }

        $found = false;
        foreach ($cart as &$item) {
            if ($item['id'] == $supplementId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $cart[] = [
                'id' => $supplementId,
                'quantity' => $quantity,
            ];
        }

        $session->set('cart', $cart);

        return new JsonResponse([
            'success' => true,
            'message' => 'Product added to cart',
            'cartCount' => count($cart),
        ]);
    }

    #[Route('/cart/update', name: 'app_shop_cart_update', methods: ['POST'])]
    public function updateCart(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$session->isStarted()) {
            $session->start();
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $supplementId = $data['supplementId'] ?? null;
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        
        if (!$supplementId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid supplement ID'], 400);
        }

        $supplement = $this->supplementRepository->find($supplementId);
        if (!$supplement) {
            return new JsonResponse(['success' => false, 'message' => 'Supplement not found'], 404);
        }

        $availableStock = (int) ($supplement->getStock() ?? 0);
        if ($quantity > $availableStock) {
            $substitutes = $this->smartSubstituteEngine->suggestForSupplement($supplement, $quantity, 4);

            return new JsonResponse([
                'success' => false,
                'message' => sprintf(
                    'Requested quantity exceeds stock. Available: %d',
                    $availableStock
                ),
                'substitutes' => $this->formatSubstituteSuggestions($substitutes),
            ], 409);
        }
        
        $cart = $session->get('cart', []);
        
        foreach ($cart as &$item) {
            if ($item['id'] == $supplementId) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        
        $session->set('cart', $cart);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cart/remove', name: 'app_shop_cart_remove', methods: ['POST'])]
    public function removeFromCart(Request $request, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $supplementId = $data['supplementId'] ?? null;

        if (!$supplementId) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid supplement ID'], 400);
        }

        $cart = $session->get('cart', []);
        $cart = array_filter($cart, fn($item) => $item['id'] != $supplementId);
        $session->set('cart', array_values($cart));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/cart/get', name: 'app_shop_cart_get', methods: ['GET'])]
    public function getCart(SessionInterface $session): JsonResponse
    {
        // Ensure session is started
        if (!$session->isStarted()) {
            $session->start();
        }

        $cart = $session->get('cart', []);
        return new JsonResponse(['cart' => $cart, 'count' => count($cart)]);
    }

    #[Route('/ai-suggestions', name: 'app_shop_ai_suggestions', methods: ['POST'])]
    public function aiSuggestions(Request $request, SessionInterface $session): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $query = trim((string) ($payload['query'] ?? ''));
        if (strlen($query) > 240) {
            $query = substr($query, 0, 240);
        }
        $normalizedQuery = strtolower(preg_replace('/\s+/', ' ', $query) ?? '');
        if ($normalizedQuery === '') {
            $normalizedQuery = '__default__';
        }

        $limit = max(1, min(8, (int) ($payload['limit'] ?? 6)));
        $focusProductId = (int) ($payload['focusProductId'] ?? 0);
        $focusProduct = $focusProductId > 0 ? $this->supplementRepository->find($focusProductId) : null;
        $excludeIds = [];
        foreach ((array) ($payload['excludeIds'] ?? []) as $excludeId) {
            $excludeId = (int) $excludeId;
            if ($excludeId > 0) {
                $excludeIds[] = $excludeId;
            }
        }

        if (!$session->isStarted()) {
            $session->start();
        }

        $cart = $session->get('cart', []);
        $cartProductIds = [];
        foreach ($cart as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $cartProductIds[] = $id;
            }
        }
        $cartProductIds = array_values(array_unique($cartProductIds));
        $excludeIds = array_values(array_unique($excludeIds));

        $history = $session->get('ai_suggestion_history', []);
        if (!is_array($history)) {
            $history = [];
        }
        $historyForQuery = array_values(array_filter(
            array_map('intval', (array) ($history[$normalizedQuery] ?? [])),
            static fn (int $id): bool => $id > 0
        ));
        $combinedExclusions = array_values(array_unique(array_merge($cartProductIds, $excludeIds, $historyForQuery)));

        $rawSuggestions = $this->aiProductSuggestionService->suggest(
            $this->supplementRepository->findCatalogLimited(),
            $focusProduct instanceof Supplement ? $focusProduct : null,
            $combinedExclusions,
            $query,
            $limit
        );

        if (count($rawSuggestions) < $limit && count($historyForQuery) > 0) {
            // Relax previous-history exclusions if the result set gets too narrow.
            $relaxedExclusions = array_values(array_unique(array_merge($cartProductIds, $excludeIds)));
            $rawSuggestions = $this->aiProductSuggestionService->suggest(
                $this->supplementRepository->findCatalogLimited(),
                $focusProduct instanceof Supplement ? $focusProduct : null,
                $relaxedExclusions,
                $query,
                $limit
            );
        }

        $suggestions = [];
        foreach ($rawSuggestions as $row) {
            $supplement = $row['supplement'] ?? null;
            if (!$supplement instanceof Supplement || $supplement->getId() === null) {
                continue;
            }

            $suggestions[] = [
                'id' => (int) $supplement->getId(),
                'name' => (string) $supplement->getName(),
                'brand' => (string) $supplement->getBrand(),
                'category' => (string) $supplement->getCategory(),
                'price' => (float) $supplement->getPrice(),
                'stock' => (int) ($supplement->getStock() ?? 0),
                'image' => $supplement->getImage()
                    ? '/uploads/supplements/' . ltrim((string) $supplement->getImage(), '/')
                    : '/images/image_1.jpg',
                'reason' => (string) ($row['reason'] ?? 'Good fit for your supplement plan.'),
                'score' => round((float) ($row['score'] ?? 0), 2),
                'source' => (string) ($row['source'] ?? 'heuristic'),
            ];
        }

        $returnedIds = array_values(array_filter(
            array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $suggestions),
            static fn (int $id): bool => $id > 0
        ));
        $history[$normalizedQuery] = array_slice(
            array_values(array_unique(array_merge($returnedIds, $historyForQuery))),
            0,
            30
        );
        $session->set('ai_suggestion_history', $history);

        return new JsonResponse([
            'success' => true,
            'suggestions' => $suggestions,
            'meta' => [
                'query' => $query,
                'count' => count($suggestions),
                'excluded' => count($combinedExclusions),
            ],
        ]);
    }

    #[Route('/checkout', name: 'app_shop_checkout', methods: ['GET'])]
    public function checkout(SessionInterface $session): Response
    {
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            return $this->redirectToRoute('app_shop_index');
        }

        $cartItems = [];
        $subtotal = 0;

        foreach ($cart as $item) {
            $supplement = $this->supplementRepository->find($item['id']);
            if ($supplement) {
                $itemTotal = $supplement->getPrice() * $item['quantity'];
                $cartItems[] = [
                    'supplement' => $supplement,
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal,
                ];
                $subtotal += $itemTotal;
            }
        }

        // Hardcoded payment methods
        $paymentMethods = [
            ['id' => 'visa', 'name' => 'Visa', 'icon' => 'fab fa-cc-visa'],
            ['id' => 'mastercard', 'name' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard'],
            ['id' => 'paypal', 'name' => 'PayPal', 'icon' => 'fab fa-cc-paypal'],
            ['id' => 'cod', 'name' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave'],
        ];

        return $this->render('pages/supplements.html.twig', [
            'mode' => 'checkout',
            'supplements' => $this->supplementRepository->findCatalogLimited(),
            'categories' => [],
            'brands' => [],
            'ratingSummary' => [],
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    #[Route('/payment/visa', name: 'app_shop_payment_visa', methods: ['GET'])]
    public function paymentVisa(): Response
    {
        return $this->render('pages/supplements.html.twig', [
            'mode' => 'payment_visa',
            'supplements' => $this->supplementRepository->findCatalogLimited(),
            'categories' => [],
            'brands' => [],
            'ratingSummary' => [],
        ]);
    }

    #[Route('/payment/paypal', name: 'app_shop_payment_paypal', methods: ['GET'])]
    public function paymentPaypal(): Response
    {
        return $this->render('pages/supplements.html.twig', [
            'mode' => 'payment_paypal',
            'supplements' => $this->supplementRepository->findCatalogLimited(),
            'categories' => [],
            'brands' => [],
            'ratingSummary' => [],
        ]);
    }

    #[Route('/order/create', name: 'app_shop_order_create', methods: ['POST'])]
    public function createOrder(Request $request, SessionInterface $session, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            return new JsonResponse(['success' => false, 'message' => 'Cart is empty'], 400);
        }

        // Create order
        $order = new Order();
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof User) {
            $order->setUser($authenticatedUser);
        }
        $order->setFirstName($data['firstName']);
        $order->setLastName($data['lastName']);
        $order->setEmail($data['email']);
        $order->setPhone($data['phone']);
        $order->setAddress($data['address']);
        $order->setCity($data['city']);
        $order->setPostalCode($data['postalCode']);
        $order->setNotes($data['notes'] ?? null);

        // Set payment method
        $order->setPaymentMethod($data['paymentMethod'] ?? 'Unknown');

        // Validate stock and calculate totals
        $subtotal = 0;
        $stockIssues = [];
        $cartRows = [];

        foreach ($cart as $item) {
            $supplement = $this->supplementRepository->find($item['id']);
            if ($supplement) {
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $availableStock = (int) ($supplement->getStock() ?? 0);

                if ($quantity > $availableStock) {
                    $stockIssues[] = [
                        'supplement' => $this->formatSupplement($supplement),
                        'requested' => $quantity,
                        'available' => $availableStock,
                        'substitutes' => $this->formatSubstituteSuggestions(
                            $this->smartSubstituteEngine->suggestForSupplement($supplement, $quantity, 3)
                        ),
                    ];
                    continue;
                }

                $cartRows[] = [
                    'supplement' => $supplement,
                    'quantity' => $quantity,
                ];
            }
        }

        if (count($stockIssues) > 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Some products in your cart are out of stock or have limited availability.',
                'issues' => $stockIssues,
            ], 409);
        }

        if (count($cartRows) === 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No valid products were found in your cart.',
            ], 400);
        }

        foreach ($cartRows as $row) {
            /** @var Supplement $supplement */
            $supplement = $row['supplement'];
            $quantity = (int) $row['quantity'];

            $orderItem = new OrderItem();
            $orderItem->setSupplement($supplement);
            $orderItem->setQuantity($quantity);
            $orderItem->setPrice($supplement->getPrice());
            $orderItem->setTotal($supplement->getPrice() * $quantity);
            $order->addOrderItem($orderItem);

            $subtotal += $orderItem->getTotal();

            // Deduct stock immediately so the inventory stays consistent after checkout.
            $supplement->setStock(max(0, ((int) $supplement->getStock()) - $quantity));
        }

        $order->setSubtotal($subtotal);

        // Calculate shipping (7 DT, free if > 100 DT)
        $shipping = $subtotal >= 100 ? 0 : 7;
        $order->setShipping($shipping);

        // Apply discount if code provided
        $discount = 0;
        if (!empty($data['discountCode']) && strtolower($data['discountCode']) === 'ali123') {
            $discount = $subtotal * 0.10; // 10% discount
            $order->setDiscountCode($data['discountCode']);
        }
        $order->setDiscount($discount);

        // Calculate total
        $total = $subtotal + $shipping - $discount;
        $order->setTotal($total);

        // Save order
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $emailResult = $this->sendOrderConfirmationEmail($mailer, $order);

        // Clear cart
        $session->remove('cart');

        $response = [
            'success' => true,
            'orderNumber' => $order->getOrderNumber(),
            'orderId' => $order->getId(),
            'emailSent' => $emailResult['sent'],
            'emailTransport' => $emailResult['transport'],
        ];

        if (!$emailResult['sent'] && $emailResult['transport'] !== 'null_transport') {
            $response['emailWarning'] = 'Order created, but confirmation email could not be sent.';
            $response['emailError'] = $emailResult['error'];
        }

        return new JsonResponse($response);
    }

    /**
     * @return array{sent: bool, transport: string, error: string|null}
     */
    private function sendOrderConfirmationEmail(MailerInterface $mailer, Order $order): array
    {
        $recipient = trim((string) $order->getEmail());
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'transport' => 'invalid_recipient',
                'error' => 'Customer email is invalid.',
            ];
        }

        $fromAddress = $_ENV['MAILER_FROM'] ?? 'no-reply@fitopia.local';
        $fromName = $_ENV['MAILER_FROM_NAME'] ?? 'Fitopia Supplements';

        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'transport' => 'invalid_from',
                'error' => 'MAILER_FROM is invalid. Use a real sender email.',
            ];
        }

        $subject = sprintf('Order %s confirmed', $order->getOrderNumber() ?? '');
        $recipientName = trim($order->getFirstName() . ' ' . $order->getLastName());

        $email = (new TemplatedEmail())
            ->from(new Address($fromAddress, $fromName))
            ->to(new Address($recipient, $recipientName))
            ->subject($subject)
            ->htmlTemplate('emails/order_confirmation.html.twig')
            ->textTemplate('emails/order_confirmation.txt.twig')
            ->context([
                'order' => $order,
            ]);

        if ($this->isNullTransport()) {
            if ($this->sendWithNativeMailFallback($recipient, $fromAddress, $fromName, $subject, $order)) {
                return ['sent' => true, 'transport' => 'php_mail', 'error' => null];
            }

            error_log('Order confirmation email skipped: MAILER_DSN is null transport and PHP mail fallback failed.');
            return [
                'sent' => false,
                'transport' => 'null_transport',
                'error' => 'MAILER_DSN is null://null, so Symfony mailer is disabled.',
            ];
        }

        try {
            $mailer->send($email);
            return ['sent' => true, 'transport' => 'symfony_mailer', 'error' => null];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            error_log('Order confirmation email failed: ' . $error);

            if ($this->sendWithNativeMailFallback($recipient, $fromAddress, $fromName, $subject, $order)) {
                return ['sent' => true, 'transport' => 'php_mail', 'error' => null];
            }

            return [
                'sent' => false,
                'transport' => 'none',
                'error' => 'SMTP send failed: ' . $error,
            ];
        }
    }

    private function sendWithNativeMailFallback(
        string $recipient,
        string $fromAddress,
        string $fromName,
        string $subject,
        Order $order
    ): bool {
        if (!function_exists('mail')) {
            return false;
        }

        $subject = $this->sanitizeHeader($subject);
        $fromName = $this->sanitizeHeader($fromName);

        $body = sprintf(
            "Hello %s,\n\n"
            . "Thank you for your order.\n"
            . "Order number: %s\n"
            . "Total: %s DT\n"
            . "Payment: %s\n\n"
            . "We will process your order shortly.\n\n"
            . "Fitopia Supplements\n",
            trim($order->getFirstName() . ' ' . $order->getLastName()),
            (string) $order->getOrderNumber(),
            (string) $order->getTotal(),
            (string) $order->getPaymentMethod()
        );

        $headers = [
            'From: "' . $fromName . '" <' . $fromAddress . '>',
            'Reply-To: ' . $fromAddress,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return (bool) @mail($recipient, $subject, $body, implode("\r\n", $headers));
    }

    private function isNullTransport(): bool
    {
        $dsn = strtolower(trim($this->mailerDsn));
        return $dsn === '' || str_starts_with($dsn, 'null://');
    }

    private function sanitizeHeader(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    #[Route('/order/success/{orderNumber}', name: 'app_shop_order_success', methods: ['GET'])]
    public function orderSuccess(string $orderNumber, EntityManagerInterface $entityManager): Response
    {
        $order = $entityManager->getRepository(Order::class)->findOneBy(['orderNumber' => $orderNumber]);

        if (!$order) {
            return $this->redirectToRoute('app_shop_index');
        }

        return $this->render('shop/order_success.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/my-progress', name: 'app_shop_progress', methods: ['GET'])]
    public function myProgress(SupplementProgressService $progressService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please sign in to access your supplement progress.');
            return $this->redirectToRoute('app_login');
        }

        $dashboard = $progressService->buildDashboard($user);

        return $this->render('shop/progress.html.twig', [
            'summary' => $dashboard['summary'],
            'cards' => $dashboard['cards'],
        ]);
    }

    #[Route('/leaderboard', name: 'app_shop_leaderboard', methods: ['GET'])]
    public function leaderboard(Request $request, MonthlyLeaderboardService $monthlyLeaderboardService): Response
    {
        $monthParam = $request->query->get('month');
        try {
            $month = $monthlyLeaderboardService->resolveMonth(is_string($monthParam) ? $monthParam : null);
        } catch (\InvalidArgumentException) {
            $month = $monthlyLeaderboardService->resolveMonth(null);
            $this->addFlash('error', 'Invalid month format. Use YYYY-MM.');
        }

        $board = $monthlyLeaderboardService->buildMonthlyLeaderboard($month, 100);
        $winner = $monthlyLeaderboardService->getWinnerForMonth($month);
        $recentWinners = $monthlyLeaderboardService->getLatestWinners(6);

        $currentUser = $this->getUser();
        $currentUserId = $currentUser instanceof User ? $currentUser->getId() : null;

        return $this->render('shop/leaderboard.html.twig', [
            'monthKey' => $board['monthKey'],
            'monthLabel' => $board['monthLabel'],
            'entries' => $board['entries'],
            'winner' => $winner,
            'recentWinners' => $recentWinners,
            'currentUserId' => $currentUserId,
        ]);
    }

    #[Route('/my-progress/{id}/take-today', name: 'app_shop_progress_take_today', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function takeToday(
        Supplement $supplement,
        Request $request,
        SupplementProgressService $progressService
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please sign in first.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('take_today_' . $supplement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request token.');
            return $this->redirectToRoute('app_shop_progress');
        }

        $result = $progressService->markTakenToday($user, $supplement);

        if ($result['success']) {
            $this->addFlash('success', sprintf('Marked as taken today: %s', (string) $supplement->getName()));
        } elseif ($result['reason'] === 'already_marked') {
            $this->addFlash('success', sprintf('Already marked today for %s.', (string) $supplement->getName()));
        } elseif ($result['reason'] === 'not_purchased') {
            $this->addFlash('error', 'You can only track supplements that you purchased.');
        } else {
            $this->addFlash('error', 'Could not update intake status. Please try again.');
        }

        return $this->redirectToRoute('app_shop_progress');
    }

    #[Route('/product/{id}', name: 'app_shop_product_show', methods: ['GET'])]
    public function showProduct(Supplement $supplement): Response
    {
        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $reviews = method_exists($reviewRepository, 'findBySupplement')
            ? $reviewRepository->findBySupplement($supplement->getId(), 30)
            : [];
        $summary = method_exists($reviewRepository, 'getSummaryForSupplement')
            ? $reviewRepository->getSummaryForSupplement($supplement->getId())
            : ['avg' => 0.0, 'count' => 0];
        $substituteSuggestions = ($supplement->getStock() ?? 0) <= 0
            ? $this->smartSubstituteEngine->suggestForSupplement($supplement, 1, 4)
            : [];

        return $this->render('pages/supplements.html.twig', [
            'mode' => 'product',
            'supplements' => $this->supplementRepository->findCatalogLimited(),
            'categories' => [],
            'brands' => [],
            'ratingSummary' => [],
            'supplement' => $supplement,
            'reviews' => $reviews,
            'ratingAverage' => $summary['avg'],
            'ratingCount' => $summary['count'],
            'substituteSuggestions' => $substituteSuggestions,
        ]);
    }

    #[Route('/product/{id}/review', name: 'app_shop_product_review', methods: ['POST'])]
    public function addReview(Request $request, Supplement $supplement): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Please sign in to leave a review.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('review' . $supplement->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid form submission.');
            return $this->redirectToRoute('app_shop_product_show', ['id' => $supplement->getId()]);
        }

        $name = trim((string) $user->getUsername());
        $email = trim((string) $user->getEmail());
        $rating = (int) $request->request->get('rating');
        $comment = trim((string) $request->request->get('comment'));

        if ($rating < 1 || $rating > 5 || $comment === '') {
            $this->addFlash('error', 'Please provide a rating and comment.');
            return $this->redirectToRoute('app_shop_product_show', ['id' => $supplement->getId()]);
        }

        $name = substr(strip_tags($name), 0, 100);
        $email = $email !== '' ? substr(strip_tags($email), 0, 180) : null;
        $comment = substr(strip_tags($comment), 0, 2000);

        $review = new Review();
        $review->setSupplement($supplement);
        $review->setName($name);
        $review->setEmail($email);
        $review->setRating($rating);
        $review->setComment($comment);

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        $this->addFlash('success', 'Thank you for your review!');

        return $this->redirectToRoute('app_shop_product_show', ['id' => $supplement->getId()]);
    }

    #[Route('/notifications', name: 'app_shop_notifications', methods: ['GET'])]
    public function notifications(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        $email = $request->query->get('email');
        if (!$email) {
            return new JsonResponse(['success' => false, 'message' => 'Email is required.'], 400);
        }

        $notifications = $notificationRepository->findByEmail($email, 20);
        $unreadCount = $notificationRepository->countUnreadByEmail($email);

        $data = array_map(function ($notification) {
            return [
                'id' => $notification->getId(),
                'orderNumber' => $notification->getOrderNumber(),
                'status' => $notification->getStatus(),
                'message' => $notification->getMessage(),
                'createdAt' => $notification->getCreatedAt()?->format(DATE_ATOM),
                'readAt' => $notification->getReadAt()?->format(DATE_ATOM),
            ];
        }, $notifications);

        return new JsonResponse([
            'success' => true,
            'unreadCount' => $unreadCount,
            'notifications' => $data,
        ]);
    }

    #[Route('/notifications/mark-read', name: 'app_shop_notifications_mark_read', methods: ['POST'])]
    public function markNotificationsRead(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['success' => false, 'message' => 'Email is required.'], 400);
        }

        $updated = $notificationRepository->markAllReadForEmail($email);

        return new JsonResponse([
            'success' => true,
            'updated' => $updated,
        ]);
    }
}

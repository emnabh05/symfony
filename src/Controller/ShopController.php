<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Review;
use App\Entity\Supplement;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\SupplementRepository;
use App\Service\SupplementProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shop')]
class ShopController extends AbstractController
{
    public function __construct(
        private SupplementRepository $supplementRepository,
        private EntityManagerInterface $entityManager,
        #[Autowire('%env(string:MAILER_DSN)%')]
        private readonly string $mailerDsn = 'null://null',
    ) {
    }

    #[Route('', name: 'app_shop_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $supplements = $this->supplementRepository->findCatalogLimited();
        $supplementIds = array_map(static fn($supplement) => $supplement->getId(), $supplements);
        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $ratingSummary = method_exists($reviewRepository, 'getSummaryForSupplements')
            ? $reviewRepository->getSummaryForSupplements($supplementIds)
            : [];
        
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

    #[Route('/favorites', name: 'app_shop_favorites', methods: ['GET'])]
    public function favorites(): Response
    {
        $supplements = $this->supplementRepository->findCatalogLimited();
        $supplementIds = array_map(static fn ($supplement) => $supplement->getId(), $supplements);
        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $ratingSummary = method_exists($reviewRepository, 'getSummaryForSupplements')
            ? $reviewRepository->getSummaryForSupplements($supplementIds)
            : [];

        $categories = array_unique(array_map(fn ($s) => $s->getCategory(), $supplements));
        $brands = array_unique(array_map(fn ($s) => $s->getBrand(), $supplements));

        sort($categories);
        sort($brands);

        return $this->render('pages/supplements.html.twig', [
            'mode' => 'favorites',
            'supplements' => $supplements,
            'categories' => $categories,
            'brands' => $brands,
            'ratingSummary' => $ratingSummary,
            'paymentMethods' => [],
        ]);
    }

    #[Route('/product/{id}', name: 'app_shop_product_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function productShow(Supplement $supplement): Response
    {
        $supplements = $this->supplementRepository->findCatalogLimited();
        $supplementIds = array_values(array_unique(array_filter(array_map(
            static fn (Supplement $item): ?int => $item->getId(),
            array_merge($supplements, [$supplement])
        ))));

        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $ratingSummary = method_exists($reviewRepository, 'getSummaryForSupplements')
            ? $reviewRepository->getSummaryForSupplements($supplementIds)
            : [];
        $productSummary = method_exists($reviewRepository, 'getSummaryForSupplement')
            ? $reviewRepository->getSummaryForSupplement((int) $supplement->getId())
            : ['avg' => 0.0, 'count' => 0];
        $reviews = method_exists($reviewRepository, 'findBySupplement')
            ? $reviewRepository->findBySupplement((int) $supplement->getId(), 30)
            : [];

        $categories = array_unique(array_map(fn ($s) => $s->getCategory(), $supplements));
        $brands = array_unique(array_map(fn ($s) => $s->getBrand(), $supplements));
        sort($categories);
        sort($brands);

        return $this->render('pages/supplements.html.twig', [
            'mode' => 'product',
            'supplements' => $supplements,
            'categories' => $categories,
            'brands' => $brands,
            'ratingSummary' => $ratingSummary,
            'paymentMethods' => [],
            'supplement' => $supplement,
            'selectedSupplement' => $supplement,
            'reviews' => $reviews,
            'ratingAverage' => $productSummary['avg'] ?? 0.0,
            'ratingCount' => $productSummary['count'] ?? 0,
        ]);
    }

    #[Route('/product/{id}/review', name: 'app_shop_product_review', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function productReview(Supplement $supplement, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please sign in to leave a review.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('review'.$supplement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid review request.');

            return $this->redirectToRoute('app_shop_product_show', ['id' => $supplement->getId()]);
        }

        $rating = max(1, min(5, (int) $request->request->get('rating', 0)));
        $comment = trim((string) $request->request->get('comment', ''));
        if ($comment === '') {
            $this->addFlash('error', 'Comment is required.');

            return $this->redirectToRoute('app_shop_product_show', ['id' => $supplement->getId()]);
        }

        $review = new Review();
        $review
            ->setSupplement($supplement)
            ->setName(trim(($user->getFirstName() ?? '').' '.($user->getLastName() ?? '')) ?: ($user->getUsername() ?: $user->getEmail()))
            ->setEmail($user->getEmail())
            ->setRating($rating)
            ->setComment($comment);

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        $this->addFlash('success', 'Review submitted successfully.');

        return $this->redirectToRoute('app_shop_product_show', ['id' => $supplement->getId()]);
    }

    #[Route('/cart', name: 'app_shop_cart', methods: ['GET'])]
    public function cart(SessionInterface $session): Response
    {
        if (!$session->isStarted()) {
            $session->start();
        }

        $cart = $session->get('cart', []);
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
        $availableStock = (int) $supplement->getStock();
        if ($requestedTotal > $availableStock) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf('Only %d unit(s) available.', $availableStock),
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

        $availableStock = (int) $supplement->getStock();
        if ($quantity > $availableStock) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf('Requested quantity exceeds stock. Available: %d', $availableStock),
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
        if (!$session->isStarted()) {
            $session->start();
        }

        $cart = $session->get('cart', []);
        return new JsonResponse(['cart' => $cart, 'count' => count($cart)]);
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

    #[Route('/order/create', name: 'app_shop_order_create', methods: ['POST'])]
    public function createOrder(Request $request, SessionInterface $session, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            return new JsonResponse(['success' => false, 'message' => 'Cart is empty'], 400);
        }

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
        $order->setPaymentMethod($data['paymentMethod'] ?? 'Unknown');

        $subtotal = 0;
        foreach ($cart as $item) {
            $supplement = $this->supplementRepository->find($item['id']);
            if ($supplement) {
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $orderItem = new OrderItem();
                $orderItem->setSupplement($supplement);
                $orderItem->setQuantity($quantity);
                $price = (float) $supplement->getPrice();
                $orderItem->setPrice(number_format($price, 2, '.', ''));
                $orderItem->setTotal(number_format($price * $quantity, 2, '.', ''));
                $order->addOrderItem($orderItem);
                $subtotal += $orderItem->getTotal();
                $supplement->setStock(max(0, ((int) $supplement->getStock()) - $quantity));
            }
        }

        $order->setSubtotal($subtotal);
        $shipping = $subtotal >= 100 ? 0.0 : 7.0;
        $order->setShipping(number_format($shipping, 2, '.', ''));
        $order->setTotal(number_format($subtotal + $shipping, 2, '.', ''));

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $session->remove('cart');

        return new JsonResponse([
            'success' => true,
            'orderNumber' => $order->getOrderNumber(),
        ]);
    }

    #[Route('/ai-suggestions', name: 'app_shop_ai_suggestions', methods: ['POST'])]
    public function aiSuggestions(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $query = trim((string) ($payload['query'] ?? ''));
        $focusProductId = (int) ($payload['focusProductId'] ?? 0);
        $limit = max(1, min(12, (int) ($payload['limit'] ?? 6)));
        $excludeIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            is_array($payload['excludeIds'] ?? null) ? $payload['excludeIds'] : []
        ), static fn (int $id): bool => $id > 0)));

        $catalog = $this->supplementRepository->findInStockCatalog(180);
        if ($catalog === []) {
            return new JsonResponse(['suggestions' => []]);
        }

        $focusSupplement = $focusProductId > 0 ? $this->supplementRepository->find($focusProductId) : null;
        $focusCategory = $focusSupplement instanceof Supplement ? strtolower(trim((string) $focusSupplement->getCategory())) : '';
        $focusBrand = $focusSupplement instanceof Supplement ? strtolower(trim((string) $focusSupplement->getBrand())) : '';

        $reviewRepository = $this->entityManager->getRepository(Review::class);
        $ratingSummary = method_exists($reviewRepository, 'getSummaryForSupplements')
            ? $reviewRepository->getSummaryForSupplements(array_map(
                static fn (Supplement $supplement): ?int => $supplement->getId(),
                $catalog
            ))
            : [];

        $queryLower = strtolower($query);
        $goalProfile = $this->buildGoalProfile($queryLower);
        $rows = [];

        foreach ($catalog as $supplement) {
            $supplementId = (int) ($supplement->getId() ?? 0);
            if ($supplementId <= 0 || in_array($supplementId, $excludeIds, true)) {
                continue;
            }

            $haystack = strtolower(trim(implode(' ', array_filter([
                $supplement->getName(),
                $supplement->getBrand(),
                $supplement->getCategory(),
                $supplement->getDescription(),
                $supplement->getManufacturer(),
            ]))));

            $score = 12.0;
            $reasonParts = [];

            if ($focusSupplement instanceof Supplement && $supplementId === (int) $focusSupplement->getId()) {
                continue;
            }

            if ($queryLower !== '') {
                $keywordMatches = 0;
                foreach ($goalProfile['keywords'] as $keyword) {
                    if ($keyword !== '' && str_contains($haystack, $keyword)) {
                        $keywordMatches++;
                    }
                }

                if ($keywordMatches > 0) {
                    $score += min(42, $keywordMatches * 9);
                    $reasonParts[] = 'matches your goal';
                }
            }

            if ($focusCategory !== '' && strtolower((string) $supplement->getCategory()) === $focusCategory) {
                $score += 18;
                $reasonParts[] = 'same category as your selected product';
            }

            if ($focusBrand !== '' && strtolower((string) $supplement->getBrand()) === $focusBrand) {
                $score += 8;
                $reasonParts[] = 'same brand family';
            }

            if (in_array(strtolower((string) $supplement->getCategory()), $goalProfile['preferredCategories'], true)) {
                $score += 20;
                $reasonParts[] = 'fits this training objective';
            }

            $summary = $ratingSummary[$supplementId] ?? ['avg' => 0.0, 'count' => 0];
            $score += min(14, ((float) ($summary['avg'] ?? 0.0)) * 2.2);
            $score += min(10, ((int) ($summary['count'] ?? 0)) * 0.8);
            $score += min(10, max(0, (int) $supplement->getStock()) / 8);

            if ($queryLower === '') {
                $reasonParts[] = 'popular in-stock option';
            }

            $rows[] = [
                'entity' => $supplement,
                'score' => round(min(99, max(1, $score)), 1),
                'reason' => $this->buildSuggestionReason($reasonParts, $goalProfile['label']),
                'source' => $queryLower !== '' ? 'ai' : 'heuristic',
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            /** @var Supplement $leftSupplement */
            $leftSupplement = $left['entity'];
            /** @var Supplement $rightSupplement */
            $rightSupplement = $right['entity'];

            return strcmp((string) $leftSupplement->getName(), (string) $rightSupplement->getName());
        });

        $suggestions = array_map(function (array $row): array {
            /** @var Supplement $supplement */
            $supplement = $row['entity'];

            return [
                'id' => (int) $supplement->getId(),
                'name' => (string) $supplement->getName(),
                'brand' => (string) $supplement->getBrand(),
                'category' => (string) $supplement->getCategory(),
                'price' => (float) $supplement->getPrice(),
                'image' => $supplement->getImage()
                    ? '/uploads/supplements/'.$supplement->getImage()
                    : '/images/image_1.jpg',
                'stock' => (int) $supplement->getStock(),
                'score' => (float) $row['score'],
                'reason' => (string) $row['reason'],
                'source' => (string) $row['source'],
            ];
        }, array_slice($rows, 0, $limit));

        return new JsonResponse(['suggestions' => $suggestions]);
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
        } else {
            $this->addFlash('error', 'Could not update intake status.');
        }

        return $this->redirectToRoute('app_shop_progress');
    }

    #[Route('/leaderboard', name: 'app_shop_leaderboard', methods: ['GET'])]
    public function leaderboard(Request $request): Response
    {
        $monthKey = trim((string) $request->query->get('month', (new \DateTimeImmutable('first day of this month'))->format('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = (new \DateTimeImmutable('first day of this month'))->format('Y-m');
        }

        $monthStart = new \DateTimeImmutable($monthKey.'-01');
        $monthEnd = $monthStart->modify('last day of this month');
        $evaluationDate = $monthEnd > new \DateTimeImmutable('today')
            ? new \DateTimeImmutable('today')
            : $monthEnd;

        $logRepository = $this->entityManager->getRepository(\App\Entity\SupplementIntakeLog::class);
        $plans = method_exists($logRepository, 'findActivePlanRows')
            ? $logRepository->findActivePlanRows()
            : [];
        $displayNames = method_exists($logRepository, 'findOrderDisplayNames')
            ? $logRepository->findOrderDisplayNames()
            : [];

        $entries = [];
        if ($plans !== []) {
            $globalStart = $monthStart;
            foreach ($plans as $plan) {
                $planStart = $this->normalizeLeaderboardDate($plan['startDate'] ?? null);
                if ($planStart instanceof \DateTimeImmutable && $planStart < $globalStart) {
                    $globalStart = $planStart;
                }
            }

            $planIds = array_values(array_filter(array_map(
                static fn (array $plan): int => (int) ($plan['planId'] ?? 0),
                $plans
            )));
            $loggedRows = method_exists($logRepository, 'findLoggedUnitsByPlanIdsBetween')
                ? $logRepository->findLoggedUnitsByPlanIdsBetween($planIds, $globalStart, $evaluationDate)
                : [];

            $unitsByPlan = [];
            foreach ($loggedRows as $row) {
                $planId = (int) ($row['planId'] ?? 0);
                $logDate = $this->normalizeLeaderboardDate($row['logDate'] ?? null);
                if ($planId <= 0 || !$logDate instanceof \DateTimeImmutable) {
                    continue;
                }

                $unitsByPlan[$planId][$logDate->format('Y-m-d')] = max(0, (int) ($row['units'] ?? 0));
            }

            $plansByUser = [];
            foreach ($plans as $plan) {
                $email = mb_strtolower(trim((string) ($plan['userEmail'] ?? '')));
                if ($email === '') {
                    continue;
                }
                $plansByUser[$email][] = $plan;
            }

            foreach ($plansByUser as $email => $userPlans) {
                $longestActiveStreak = 0;
                $totalMonthExpectedDays = 0;
                $totalMonthAdherentDays = 0;
                $goalCompletionAccumulator = 0.0;
                $goalCompletionCount = 0;

                foreach ($userPlans as $plan) {
                    $planId = (int) ($plan['planId'] ?? 0);
                    $planStart = $this->normalizeLeaderboardDate($plan['startDate'] ?? null) ?? $monthStart;
                    $plannedDays = max(1, (int) ($plan['plannedDays'] ?? 1));
                    $dailyTargetUnits = max(1, (int) ($plan['dailyTargetUnits'] ?? 1));
                    $planExpectedEnd = $planStart->modify(sprintf('+%d days', max(0, $plannedDays - 1)));
                    $dayUnits = $unitsByPlan[$planId] ?? [];

                    $activeStreak = $this->computeLeaderboardActiveStreak($planStart, $planExpectedEnd, $dayUnits, $dailyTargetUnits, $evaluationDate);
                    $longestActiveStreak = max($longestActiveStreak, $activeStreak);

                    $monthExpected = $this->countLeaderboardExpectedDays($planStart, $planExpectedEnd, $monthStart, $evaluationDate);
                    $monthAdherent = $this->countLeaderboardAdherentDays($planStart, $planExpectedEnd, $dayUnits, $dailyTargetUnits, $monthStart, $evaluationDate);
                    $totalMonthExpectedDays += $monthExpected;
                    $totalMonthAdherentDays += $monthAdherent;

                    $goalCompletionAccumulator += $this->computeLeaderboardGoalCompletion($planStart, $planExpectedEnd, $plannedDays, $dayUnits, $dailyTargetUnits, $evaluationDate);
                    $goalCompletionCount++;
                }

                $entries[] = [
                    'email' => $email,
                    'displayName' => $this->resolveLeaderboardDisplayName($displayNames, $email),
                    'longestActiveStreak' => $longestActiveStreak,
                    'consistencyPercent' => $this->computeLeaderboardPercentage($totalMonthAdherentDays, $totalMonthExpectedDays),
                    'goalCompletionPercent' => $goalCompletionCount > 0 ? round($goalCompletionAccumulator / $goalCompletionCount, 1) : 0.0,
                ];
            }
        }

        usort($entries, static function (array $a, array $b): int {
            if ($a['longestActiveStreak'] !== $b['longestActiveStreak']) {
                return $b['longestActiveStreak'] <=> $a['longestActiveStreak'];
            }
            if ($a['consistencyPercent'] !== $b['consistencyPercent']) {
                return $b['consistencyPercent'] <=> $a['consistencyPercent'];
            }
            if ($a['goalCompletionPercent'] !== $b['goalCompletionPercent']) {
                return $b['goalCompletionPercent'] <=> $a['goalCompletionPercent'];
            }

            return strcasecmp((string) $a['displayName'], (string) $b['displayName']);
        });

        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
        }
        unset($entry);

        $currentUser = $this->getUser();
        $currentUserEmail = $currentUser instanceof User
            ? mb_strtolower(trim((string) $currentUser->getEmail()))
            : null;

        $notifications = ['Ranking is based on active streak, consistency %, then goal completion.'];
        if ($currentUserEmail !== null) {
            foreach ($entries as $entry) {
                if (($entry['email'] ?? null) !== $currentUserEmail) {
                    continue;
                }

                if (($entry['rank'] ?? 999) <= 5) {
                    $notifications = [
                        sprintf("You're top %d in consistency this month.", (int) $entry['rank']),
                        'Ranking is based on active streak, consistency %, then goal completion.',
                    ];
                } else {
                    $notifications = [
                        sprintf('You are currently ranked #%d.', (int) $entry['rank']),
                        'Ranking is based on active streak, consistency %, then goal completion.',
                    ];
                }

                break;
            }
        }

        return $this->render('shop/leaderboard.html.twig', [
            'monthKey' => $monthKey,
            'monthLabel' => $monthStart->format('F Y'),
            'entries' => $entries,
            'notifications' => $notifications,
            'currentUserEmail' => $currentUserEmail,
        ]);
    }

    private function normalizeLeaderboardDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTime(0, 0);
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTime(0, 0);
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->setTime(0, 0);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, int> $dayUnits
     */
    private function computeLeaderboardActiveStreak(
        \DateTimeImmutable $planStart,
        \DateTimeImmutable $planExpectedEnd,
        array $dayUnits,
        int $dailyTargetUnits,
        \DateTimeImmutable $evaluationDate
    ): int {
        $endDate = $evaluationDate < $planExpectedEnd ? $evaluationDate : $planExpectedEnd;
        if ($endDate < $planStart) {
            return 0;
        }

        $streak = 0;
        for ($cursor = $endDate; $cursor >= $planStart; $cursor = $cursor->modify('-1 day')) {
            $units = (int) ($dayUnits[$cursor->format('Y-m-d')] ?? 0);
            if ($units < $dailyTargetUnits) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function countLeaderboardExpectedDays(
        \DateTimeImmutable $planStart,
        \DateTimeImmutable $planExpectedEnd,
        \DateTimeImmutable $fromDate,
        \DateTimeImmutable $toDate
    ): int {
        $start = $planStart > $fromDate ? $planStart : $fromDate;
        $end = $planExpectedEnd < $toDate ? $planExpectedEnd : $toDate;
        if ($start > $end) {
            return 0;
        }

        return ((int) $start->diff($end)->days) + 1;
    }

    /**
     * @param array<string, int> $dayUnits
     */
    private function countLeaderboardAdherentDays(
        \DateTimeImmutable $planStart,
        \DateTimeImmutable $planExpectedEnd,
        array $dayUnits,
        int $dailyTargetUnits,
        \DateTimeImmutable $fromDate,
        \DateTimeImmutable $toDate
    ): int {
        $start = $planStart > $fromDate ? $planStart : $fromDate;
        $end = $planExpectedEnd < $toDate ? $planExpectedEnd : $toDate;
        if ($start > $end) {
            return 0;
        }

        $adherentDays = 0;
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $units = (int) ($dayUnits[$cursor->format('Y-m-d')] ?? 0);
            if ($units >= $dailyTargetUnits) {
                $adherentDays++;
            }
        }

        return $adherentDays;
    }

    /**
     * @param array<string, int> $dayUnits
     */
    private function computeLeaderboardGoalCompletion(
        \DateTimeImmutable $planStart,
        \DateTimeImmutable $planExpectedEnd,
        int $plannedDays,
        array $dayUnits,
        int $dailyTargetUnits,
        \DateTimeImmutable $evaluationDate
    ): float {
        $endDate = $evaluationDate < $planExpectedEnd ? $evaluationDate : $planExpectedEnd;
        if ($endDate < $planStart) {
            return 0.0;
        }

        $elapsedDays = ((int) $planStart->diff($endDate)->days) + 1;
        $expectedDays = min($plannedDays, max($elapsedDays, 0));
        $completedDays = $this->countLeaderboardAdherentDays(
            $planStart,
            $planExpectedEnd,
            $dayUnits,
            $dailyTargetUnits,
            $planStart,
            $endDate
        );

        return $this->computeLeaderboardPercentage($completedDays, $expectedDays);
    }

    private function computeLeaderboardPercentage(int $completed, int $expected): float
    {
        if ($expected <= 0) {
            return 0.0;
        }

        return round(($completed / $expected) * 100, 1);
    }

    /**
     * @param array<string, string> $displayNames
     */
    private function resolveLeaderboardDisplayName(array $displayNames, string $email): string
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $displayName = trim((string) ($displayNames[$normalizedEmail] ?? ''));
        if ($displayName !== '') {
            return $displayName;
        }

        $localPart = strstr($normalizedEmail, '@', true);
        return $localPart !== false && $localPart !== '' ? $localPart : $normalizedEmail;
    }

    #[Route('/order/success/{orderNumber}', name: 'app_shop_order_success', methods: ['GET'])]
    public function orderSuccess(string $orderNumber, OrderRepository $orderRepository): Response
    {
        $order = $orderRepository->findOneByOrderNumber($orderNumber);
        if (!$order instanceof Order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('shop/order_success.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * @return array{label: string, keywords: string[], preferredCategories: string[]}
     */
    private function buildGoalProfile(string $queryLower): array
    {
        $profiles = [
            [
                'label' => 'lean muscle',
                'keywords' => ['muscle', 'lean', 'strength', 'protein', 'mass', 'recovery'],
                'preferredCategories' => ['whey protein', 'mass gainer', 'creatine', 'bcaa / eaa'],
            ],
            [
                'label' => 'recovery',
                'keywords' => ['recovery', 'fatigue', 'soreness', 'repair', 'rest', 'post workout'],
                'preferredCategories' => ['bcaa / eaa', 'whey protein', 'vitamins & minerals', 'creatine'],
            ],
            [
                'label' => 'endurance',
                'keywords' => ['endurance', 'cardio', 'stamina', 'energy', 'long workout'],
                'preferredCategories' => ['pre-workout', 'bcaa / eaa', 'vitamins & minerals'],
            ],
            [
                'label' => 'fat loss',
                'keywords' => ['fat loss', 'cut', 'lean', 'diet', 'weight loss', 'burn'],
                'preferredCategories' => ['pre-workout', 'whey protein', 'vitamins & minerals'],
            ],
            [
                'label' => 'daily health',
                'keywords' => ['health', 'daily', 'wellness', 'immunity', 'vitamin', 'mineral'],
                'preferredCategories' => ['vitamins & minerals'],
            ],
        ];

        $selected = [
            'label' => $queryLower !== '' ? 'your goal' : 'daily performance',
            'keywords' => [],
            'preferredCategories' => [],
        ];
        $bestMatches = 0;

        foreach ($profiles as $profile) {
            $matches = 0;
            foreach ($profile['keywords'] as $keyword) {
                if ($keyword !== '' && str_contains($queryLower, $keyword)) {
                    $matches++;
                }
            }

            if ($matches > $bestMatches) {
                $bestMatches = $matches;
                $selected = $profile;
            }
        }

        $tokens = preg_split('/[^a-z0-9]+/', $queryLower) ?: [];
        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token !== '' && strlen($token) >= 3 && !in_array($token, $selected['keywords'], true)) {
                $selected['keywords'][] = $token;
            }
        }

        return $selected;
    }

    /**
     * @param string[] $reasonParts
     */
    private function buildSuggestionReason(array $reasonParts, string $goalLabel): string
    {
        $reasonParts = array_values(array_unique(array_filter(array_map(
            static fn (mixed $part): string => trim((string) $part),
            $reasonParts
        ))));

        if ($reasonParts === []) {
            return sprintf('Selected as a balanced Fitopia recommendation for %s.', $goalLabel);
        }

        $summary = implode(', ', array_slice($reasonParts, 0, 3));

        return sprintf('Recommended for %s: %s.', $goalLabel, $summary);
    }
}

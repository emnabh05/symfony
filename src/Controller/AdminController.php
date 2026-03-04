<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Form\ProfileFormType;
use App\Form\BlogPostType;
use App\Form\RepasFormType;
use App\Entity\BlogPost;
use App\Entity\ContentInteraction;
use App\Entity\Event;
use App\Entity\FitnessExercise;
use App\Entity\FitnessProgram;
use App\Entity\FitnessTrend;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Repas;
use App\Entity\Supplement;
use App\Entity\User;
use App\Service\OrderStatusNotifier;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

use App\Entity\Participation;
use App\Entity\WaitlistEntry;
use App\Repository\WaitlistEntryRepository;
use App\Service\EventCapacityService;
use App\Service\WaitlistService;
use App\Service\SmartAvatarService;
#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/tables', name: 'tables')]
    public function tables(): Response
    {
        return $this->render('admin/tables.html.twig');
    }

    #[Route('/users', name: 'users')]
    public function users(EntityManagerInterface $em): Response
    {
        return $this->render('admin/gestion-users.html.twig', [
            'managedUsers' => $em->getRepository(User::class)->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/blog-forum', name: 'blog_forum')]
    public function blogForum(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $post = new BlogPost();
        $form = $this->createForm(BlogPostType::class, $post, [
            'attr' => ['id' => 'blog-create-form'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $featuredImage */
            $featuredImage = $form->get('featuredImageFile')->getData();
            if ($featuredImage) {
                $originalFilename = pathinfo($featuredImage->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$featuredImage->guessExtension();

                try {
                    $featuredImage->move(
                        $this->getParameter('app.blog_upload_dir'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload the featured image. Please try again.');
                }

                $post->setFeaturedImage($newFilename);
            }

            $post->setAuthor($user);
            $post->setStatus('published');
            $post->setViewCount(0);
            $post->setCreatedAt(new \DateTimeImmutable());
            $post->setPublishedAt(new \DateTimeImmutable());

            $baseSlug = strtolower($slugger->slug($post->getTitle())->toString());
            $post->setSlug($baseSlug.'-'.substr(uniqid(), -6));

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Blog post published successfully.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $posts = $em->getRepository(BlogPost::class)->findBy([], ['createdAt' => 'DESC']);
        $allInteractions = $em->getRepository(ContentInteraction::class)->findBy(['targetType' => 'blog_post'], ['createdAt' => 'DESC']);
        $managedUsers = $em->getRepository(User::class)->findBy([], ['id' => 'DESC']);

        $interactionStats = [];
        $interactions = [];
        foreach ($allInteractions as $interaction) {
            $postId = $interaction->getTargetId();
            if (!isset($interactionStats[$postId])) {
                $interactionStats[$postId] = [
                    'like' => 0,
                    'repost' => 0,
                    'comment' => 0,
                ];
            }

            $type = $interaction->getInteractionType();
            if (isset($interactionStats[$postId][$type])) {
                $interactionStats[$postId][$type]++;
            }

            $interactions[] = [
                'id' => $interaction->getId(),
                'type' => $interaction->getInteractionType(),
                'comment' => $interaction->getCommentText() ?? '',
                'userId' => $interaction->getUser()?->getId(),
                'userEmail' => $interaction->getUser()?->getEmail() ?? '',
                'postId' => $postId,
                'postTitle' => '',
                'date' => $interaction->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }

        $postsList = [];
        $postTitleById = [];
        foreach ($posts as $p) {
            $postTitleById[$p->getId()] = $p->getTitle();
            $postsList[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
            ];
        }

        foreach ($interactions as $k => $row) {
            $interactions[$k]['postTitle'] = $postTitleById[$row['postId']] ?? ('Post #'.$row['postId']);
        }

        $usersList = [];
        foreach ($managedUsers as $u) {
            $usersList[] = [
                'id' => $u->getId(),
                'email' => $u->getEmail(),
                'username' => $u->getUsername(),
            ];
        }

        $items = [];
        $editForms = [];
        foreach ($posts as $p) {
            $roles = $p->getAuthor()?->getRoles() ?? [];
            $role = $roles[0] ?? 'ROLE_USER';
            $stats = $interactionStats[$p->getId()] ?? ['like' => 0, 'repost' => 0, 'comment' => 0];
            $items[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'date' => $p->getCreatedAt()->format('Y-m-d'),
                'email' => $p->getAuthor()?->getEmail() ?? '',
                'role' => $role,
                'category' => $p->getCategory() ?? '-',
                'status' => $p->getStatus(),
                'likes' => $stats['like'],
                'reposts' => $stats['repost'],
                'comments' => $stats['comment'],
                'excerpt' => $p->getExcerpt() ?? '',
                'image' => $p->getFeaturedImage(),
            ];

            $editForms[$p->getId()] = $this->createForm(BlogPostType::class, $p, [
                'action' => $this->generateUrl('admin_blog_forum_edit', ['id' => $p->getId()]),
                'attr' => ['class' => 'blog-edit-form', 'data-post-id' => $p->getId()],
            ])->createView();
        }

        $publishedPosts = 0;
        $draftPosts = 0;
        $activeAuthorIds = [];
        $categoryCounts = [];
        foreach ($posts as $p) {
            $status = strtolower((string) $p->getStatus());
            if ($status === 'published') {
                $publishedPosts++;
            } else {
                $draftPosts++;
            }

            if ($p->getAuthor()?->getId() !== null) {
                $activeAuthorIds[(int) $p->getAuthor()->getId()] = true;
            }

            $category = trim((string) ($p->getCategory() ?? 'Uncategorized'));
            $category = $category !== '' ? $category : 'Uncategorized';
            if (!isset($categoryCounts[$category])) {
                $categoryCounts[$category] = 0;
            }
            $categoryCounts[$category]++;
        }

        $interactionCounts = ['like' => 0, 'repost' => 0, 'comment' => 0];
        foreach ($allInteractions as $interaction) {
            $type = strtolower((string) $interaction->getInteractionType());
            if (isset($interactionCounts[$type])) {
                $interactionCounts[$type]++;
            }
        }

        arsort($categoryCounts);
        $topCategory = array_key_first($categoryCounts);

        $seriesMap = [];
        $startDay = (new \DateTimeImmutable('today'))->modify('-6 days');
        for ($i = 0; $i < 7; $i++) {
            $day = $startDay->modify('+'.$i.' days');
            $seriesMap[$day->format('Y-m-d')] = [
                'label' => strtoupper($day->format('D')),
                'count' => 0,
            ];
        }
        foreach ($posts as $p) {
            $createdAt = $p->getCreatedAt();
            if (!$createdAt) {
                continue;
            }
            $dayKey = $createdAt->format('Y-m-d');
            if (isset($seriesMap[$dayKey])) {
                $seriesMap[$dayKey]['count']++;
            }
        }
        $weeklySeries = array_values($seriesMap);
        $weeklyMax = 1;
        foreach ($weeklySeries as $point) {
            $weeklyMax = max($weeklyMax, (int) $point['count']);
        }

        $blogStats = [
            'totalPosts' => count($posts),
            'publishedPosts' => $publishedPosts,
            'draftPosts' => $draftPosts,
            'totalInteractions' => count($allInteractions),
            'likes' => $interactionCounts['like'],
            'reposts' => $interactionCounts['repost'],
            'comments' => $interactionCounts['comment'],
            'activeAuthors' => count($activeAuthorIds),
            'engagementPerPost' => count($posts) > 0 ? round(count($allInteractions) / count($posts), 2) : 0,
            'topCategory' => $topCategory ?: '-',
            'weeklySeries' => $weeklySeries,
            'weeklyMax' => $weeklyMax,
        ];

        return $this->render('admin/gestion-blog-forum.html.twig', [
            'blogForm' => $form->createView(),
            'items' => $items,
            'editForms' => $editForms,
            'interactions' => $interactions,
            'users' => $usersList,
            'posts' => $postsList,
            'blogStats' => $blogStats,
        ]);
    }

    #[Route('/blog-forum/{id}/edit', name: 'blog_forum_edit', methods: ['GET', 'POST'])]
    public function editBlogPost(
        BlogPost $post,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('admin_blog_forum');
        }
        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $featuredImage */
            $featuredImage = $form->get('featuredImageFile')->getData();
            if ($featuredImage) {
                $originalFilename = pathinfo($featuredImage->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$featuredImage->guessExtension();

                try {
                    $featuredImage->move(
                        $this->getParameter('app.blog_upload_dir'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload the featured image. Please try again.');
                }

                $post->setFeaturedImage($newFilename);
            }

            $baseSlug = strtolower($slugger->slug($post->getTitle())->toString());
            $post->setSlug($baseSlug.'-'.substr(uniqid(), -6));
            $post->setUpdatedAt(new \DateTimeImmutable());

            $em->flush();
            $this->addFlash('success', 'Blog post updated successfully.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/blog-forum/{id}/delete', name: 'blog_forum_delete', methods: ['POST'])]
    public function deleteBlogPost(
        BlogPost $post,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('delete_blog_post_'.$post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $em->remove($post);
        $em->flush();
        $this->addFlash('success', 'Blog post deleted.');
        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/blog-forum/interactions/add', name: 'blog_forum_interaction_add', methods: ['POST'])]
    public function addBlogInteraction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_blog_interaction_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $this->addFlash('info', 'Interaction create route is available. Persistence logic can be added next.');
        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/blog-forum/interactions/{id}/update', name: 'blog_forum_interaction_update', methods: ['POST'])]
    public function updateBlogInteraction(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_blog_interaction_update_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $this->addFlash('info', 'Interaction update route is available. Persistence logic can be added next.');
        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/blog-forum/interactions/{id}/delete', name: 'blog_forum_interaction_delete', methods: ['POST'])]
    public function deleteBlogInteraction(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_blog_interaction_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $this->addFlash('info', 'Interaction delete route is available. Persistence logic can be added next.');
        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/stocks', name: 'stocks')]
    public function stocks(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $supplements = $em->getRepository(Supplement::class)->findBy([], ['updatedAt' => 'DESC']);
        $payments = [];
        $stats = [
            'weeklyRevenue' => 0.0,
            'weeklyOrders' => 0,
            'monthlyRevenue' => 0.0,
            'monthlyOrders' => 0,
            'totalRevenue' => 0.0,
            'totalOrders' => 0,
            'avgOrderValue' => 0.0,
            'itemsSold' => 0,
            'statusCounts' => [
                'pending' => 0,
                'accepted' => 0,
                'delivered' => 0,
                'canceled' => 0,
            ],
            'weeklySeries' => [],
            'topProducts' => [],
            'bestProduct' => null,
            'lowStock' => [],
            'lowStockCount' => 0,
        ];

        $lowStock = array_values(array_filter($supplements, static function (Supplement $supplement): bool {
            return $supplement->getStock() <= 10;
        }));
        usort($lowStock, static function (Supplement $a, Supplement $b): int {
            return $a->getStock() <=> $b->getStock();
        });
        $stats['lowStock'] = array_slice($lowStock, 0, 5);
        $stats['lowStockCount'] = count($lowStock);

        $now = new \DateTimeImmutable('now');
        $weekStart = $now->modify('-6 days')->setTime(0, 0, 0);
        $monthStart = $now->modify('-30 days')->setTime(0, 0, 0);
        $weeklySeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->modify('-'.$i.' days');
            $key = $date->format('Y-m-d');
            $weeklySeries[$key] = [
                'label' => $date->format('D'),
                'date' => $date->format('M d'),
                'value' => 0.0,
                'percent' => 0,
            ];
        }

        if ($this->tableExists($em, 'order')) {
            $payments = $em->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC']);

            $recentOrders = $em->getRepository(Order::class)->createQueryBuilder('o')
                ->andWhere('o.createdAt >= :monthStart')
                ->andWhere('o.status != :canceled')
                ->setParameter('monthStart', $monthStart)
                ->setParameter('canceled', 'canceled')
                ->orderBy('o.createdAt', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($recentOrders as $order) {
                $createdAt = $order->getCreatedAt();
                $total = (float) $order->getTotal();
                $stats['monthlyRevenue'] += $total;
                $stats['monthlyOrders']++;

                if ($createdAt && $createdAt >= $weekStart) {
                    $stats['weeklyRevenue'] += $total;
                    $stats['weeklyOrders']++;
                }

                if ($createdAt) {
                    $key = $createdAt->format('Y-m-d');
                    if (isset($weeklySeries[$key])) {
                        $weeklySeries[$key]['value'] += $total;
                    }
                }
            }

            $maxWeekly = 0.0;
            foreach ($weeklySeries as $point) {
                if ($point['value'] > $maxWeekly) {
                    $maxWeekly = $point['value'];
                }
            }
            if ($maxWeekly > 0) {
                foreach ($weeklySeries as $key => $point) {
                    $weeklySeries[$key]['percent'] = (int) round(($point['value'] / $maxWeekly) * 100);
                }
            }

            $totalStats = $em->createQueryBuilder()
                ->select('COUNT(o.id) as orderCount', 'COALESCE(SUM(o.total), 0) as revenue')
                ->from(Order::class, 'o')
                ->andWhere('o.status != :canceled')
                ->setParameter('canceled', 'canceled')
                ->getQuery()
                ->getSingleResult();

            $stats['totalRevenue'] = (float) $totalStats['revenue'];
            $revenueOrders = (int) $totalStats['orderCount'];
            $stats['avgOrderValue'] = $revenueOrders > 0 ? $stats['totalRevenue'] / $revenueOrders : 0.0;

            $statusRows = $em->createQueryBuilder()
                ->select('o.status as status', 'COUNT(o.id) as count')
                ->from(Order::class, 'o')
                ->groupBy('o.status')
                ->getQuery()
                ->getArrayResult();
            $statusCounts = $stats['statusCounts'];
            foreach ($statusRows as $row) {
                $status = (string) ($row['status'] ?? '');
                if ($status !== '') {
                    $statusCounts[$status] = (int) $row['count'];
                }
            }
            $stats['statusCounts'] = $statusCounts;
            $stats['totalOrders'] = array_sum($statusCounts);

            if ($this->tableExists($em, 'order_item')) {
                $itemsSold = $em->createQueryBuilder()
                    ->select('COALESCE(SUM(oi.quantity), 0)')
                    ->from(OrderItem::class, 'oi')
                    ->join('oi.order', 'o')
                    ->andWhere('o.status != :canceled')
                    ->setParameter('canceled', 'canceled')
                    ->getQuery()
                    ->getSingleScalarResult();
                $stats['itemsSold'] = (int) $itemsSold;

                $topProducts = $em->createQueryBuilder()
                    ->select('s.id as id', 's.name as name', 's.brand as brand', 'SUM(oi.quantity) as qty', 'COALESCE(SUM(oi.total), 0) as revenue')
                    ->from(OrderItem::class, 'oi')
                    ->join('oi.order', 'o')
                    ->join('oi.supplement', 's')
                    ->andWhere('o.status != :canceled')
                    ->andWhere('o.createdAt >= :monthStart')
                    ->setParameter('canceled', 'canceled')
                    ->setParameter('monthStart', $monthStart)
                    ->groupBy('s.id, s.name, s.brand')
                    ->orderBy('qty', 'DESC')
                    ->setMaxResults(5)
                    ->getQuery()
                    ->getArrayResult();

                $stats['topProducts'] = $topProducts;
                $stats['bestProduct'] = $topProducts[0] ?? null;
            }
        } else {
            $this->addFlash('error', 'Payments table is missing. Run migrations to enable payment management.');
        }

        $stats['weeklySeries'] = array_values($weeklySeries);

        return $this->render('admin/gestion-stocks.html.twig', [
            'supplements' => $supplements,
            'payments' => $payments,
            'stats' => $stats,
        ]);
    }

    #[Route('/stocks/supplement/add', name: 'stocks_supplement_add', methods: ['GET', 'POST'])]
    public function stocksSupplementAdd(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($request->isMethod('GET')) {
            return $this->render('admin/stocks_supplement_new.html.twig');
        }
        if (!$this->isCsrfTokenValid('admin_stocks_supplement_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }

        $supplement = new Supplement();
        $supplement->setName((string) $request->request->get('name'));
        $supplement->setCategory((string) $request->request->get('category'));
        $supplement->setBrand((string) $request->request->get('brand'));
        $supplement->setPrice(number_format((float) $request->request->get('price', 0), 2, '.', ''));
        $supplement->setStock($request->request->getInt('stock'));
        $supplement->setCalories($request->request->getInt('calories') ?: null);
        $supplement->setDescription((string) $request->request->get('description'));

        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image_file');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('app.supplement_upload_dir'), $newFilename);
                $supplement->setImage($newFilename);
            } catch (FileException) {
                $this->addFlash('error', 'Could not upload supplement image.');
            }
        }

        $em->persist($supplement);
        $em->flush();
        $this->addFlash('success', 'Supplement created successfully.');
        return $this->redirectToRoute('admin_stocks');
    }

    #[Route('/stocks/supplement/{id}/update', name: 'stocks_supplement_update', methods: ['GET', 'POST'])]
    public function stocksSupplementUpdate(Supplement $supplement, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($request->isMethod('GET')) {
            return $this->render('admin/stocks_supplement_edit.html.twig', [
                'supplement' => $supplement,
            ]);
        }
        if (!$this->isCsrfTokenValid('admin_stocks_supplement_update_'.$supplement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }

        $supplement->setName((string) $request->request->get('name'));
        $supplement->setCategory((string) $request->request->get('category'));
        $supplement->setBrand((string) $request->request->get('brand'));
        $supplement->setPrice(number_format((float) $request->request->get('price', 0), 2, '.', ''));
        $supplement->setStock($request->request->getInt('stock'));
        $supplement->setCalories($request->request->getInt('calories') ?: null);
        $supplement->setDescription((string) $request->request->get('description'));

        /** @var UploadedFile|null $imageFile */
        $imageFile = $request->files->get('image_file');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('app.supplement_upload_dir'), $newFilename);
                $supplement->setImage($newFilename);
            } catch (FileException) {
                $this->addFlash('error', 'Could not upload supplement image.');
            }
        }

        $em->flush();
        $this->addFlash('success', 'Supplement updated successfully.');
        return $this->redirectToRoute('admin_stocks');
    }

    #[Route('/stocks/supplement/{id}/delete', name: 'stocks_supplement_delete', methods: ['POST'])]
    public function stocksSupplementDelete(Supplement $supplement, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_stocks_supplement_delete_'.$supplement->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }
        $em->remove($supplement);
        $em->flush();
        $this->addFlash('success', 'Supplement deleted.');
        return $this->redirectToRoute('admin_stocks');
    }

    #[Route('/stocks/payment/add', name: 'stocks_payment_add', methods: ['GET', 'POST'])]
    public function stocksPaymentAdd(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->tableExists($em, 'order')) {
            $this->addFlash('error', 'Payments table is missing. Run migrations first.');
            return $this->redirectToRoute('admin_stocks');
        }
        if ($request->isMethod('GET')) {
            return $this->render('admin/stocks_payment_new.html.twig');
        }
        if (!$this->isCsrfTokenValid('admin_stocks_payment_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }

        $order = new Order();
        $customOrderNumber = trim((string) $request->request->get('order_number'));
        if ($customOrderNumber !== '') {
            $order->setOrderNumber($customOrderNumber);
        }
        $order->setFirstName((string) $request->request->get('first_name'));
        $order->setLastName((string) $request->request->get('last_name'));
        $order->setEmail((string) $request->request->get('email'));
        $order->setPhone((string) $request->request->get('phone'));
        $order->setAddress((string) $request->request->get('address'));
        $order->setCity((string) $request->request->get('city'));
        $order->setPostalCode((string) $request->request->get('postal_code'));
        $order->setPaymentMethod((string) $request->request->get('payment_method'));
        $order->setStatus((string) $request->request->get('status', 'pending'));
        $order->setSubtotal(number_format((float) $request->request->get('subtotal', 0), 2, '.', ''));
        $order->setShipping(number_format((float) $request->request->get('shipping', 0), 2, '.', ''));
        $order->setDiscount(number_format((float) $request->request->get('discount', 0), 2, '.', ''));
        $order->setTotal(number_format((float) $request->request->get('total', 0), 2, '.', ''));
        $order->setDiscountCode((string) $request->request->get('discount_code') ?: null);
        $order->setNotes((string) $request->request->get('notes') ?: null);

        $em->persist($order);
        $em->flush();
        $this->addFlash('success', 'Payment record created successfully.');
        return $this->redirectToRoute('admin_stocks');
    }

    #[Route('/stocks/payment/{id}/update', name: 'stocks_payment_update', methods: ['GET', 'POST'])]
    public function stocksPaymentUpdate(
        Order $order,
        Request $request,
        EntityManagerInterface $em,
        OrderStatusNotifier $orderStatusNotifier
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->tableExists($em, 'order')) {
            $this->addFlash('error', 'Payments table is missing. Run migrations first.');
            return $this->redirectToRoute('admin_stocks');
        }
        if ($request->isMethod('GET')) {
            return $this->render('admin/stocks_payment_edit.html.twig', [
                'order' => $order,
            ]);
        }
        if (!$this->isCsrfTokenValid('admin_stocks_payment_update_'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }

        $customOrderNumber = trim((string) $request->request->get('order_number'));
        if ($customOrderNumber !== '') {
            $order->setOrderNumber($customOrderNumber);
        }
        $oldStatus = (string) ($order->getStatus() ?? '');
        $order->setFirstName((string) $request->request->get('first_name'));
        $order->setLastName((string) $request->request->get('last_name'));
        $order->setEmail((string) $request->request->get('email'));
        $order->setPhone((string) $request->request->get('phone'));
        $order->setAddress((string) $request->request->get('address'));
        $order->setCity((string) $request->request->get('city'));
        $order->setPostalCode((string) $request->request->get('postal_code'));
        $order->setPaymentMethod((string) $request->request->get('payment_method'));
        $newStatus = (string) $request->request->get('status', 'pending');
        $order->setStatus($newStatus);
        $order->setSubtotal(number_format((float) $request->request->get('subtotal', 0), 2, '.', ''));
        $order->setShipping(number_format((float) $request->request->get('shipping', 0), 2, '.', ''));
        $order->setDiscount(number_format((float) $request->request->get('discount', 0), 2, '.', ''));
        $order->setTotal(number_format((float) $request->request->get('total', 0), 2, '.', ''));
        $order->setDiscountCode((string) $request->request->get('discount_code') ?: null);
        $order->setNotes((string) $request->request->get('notes') ?: null);

        $em->flush();

        if ($oldStatus !== $newStatus) {
            $notifyResult = $orderStatusNotifier->notifyStatusChange($order, $oldStatus, $newStatus);
            if (strtolower($newStatus) === 'delivered' && !$notifyResult['emailSent']) {
                $this->addFlash('warning', 'Order marked as delivered, but delivery email was not sent.');
            }
        }

        $this->addFlash('success', 'Payment record updated successfully.');
        return $this->redirectToRoute('admin_stocks');
    }

    #[Route('/stocks/payment/{id}/delete', name: 'stocks_payment_delete', methods: ['POST'])]
    public function stocksPaymentDelete(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->tableExists($em, 'order')) {
            $this->addFlash('error', 'Payments table is missing. Run migrations first.');
            return $this->redirectToRoute('admin_stocks');
        }
        if (!$this->isCsrfTokenValid('admin_stocks_payment_delete_'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }

        $em->remove($order);
        $em->flush();
        $this->addFlash('success', 'Payment record deleted.');
        return $this->redirectToRoute('admin_stocks');
    }

    #[Route('/events', name: 'events')]
    public function events(
        Request $request,
        EntityManagerInterface $em,
        WaitlistEntryRepository $waitlistEntryRepository,
        EventCapacityService $eventCapacityService
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'recent');
        $premiumFilter = (string) $request->query->get('premium_filter', 'all');
        if (!in_array($sort, ['recent', 'oldest'], true)) {
            $sort = 'recent';
        }
        if (!in_array($premiumFilter, ['all', 'premium', 'standard'], true)) {
            $premiumFilter = 'all';
        }

        if (!$this->tableExists($em, 'events') || !$this->tableExists($em, 'reservation')) {
            $this->addFlash('error', 'Events/reservation tables are missing. Import/migrate events schema first.');
            return $this->render('admin/gestion-events.html.twig', [
                'events' => [],
                'eventStats' => [],
                'participantsList' => [],
                'totalEvents' => 0,
                'totalParticipants' => 0,
                'totalFull' => 0,
                'totalPremium' => 0,
                'search' => $search,
                'sort' => $sort,
                'premiumFilter' => $premiumFilter,
                'waitlistByEvent' => [],
            ]);
        }

        $eventsQb = $em->getRepository(Event::class)->createQueryBuilder('e')
            ->orderBy('e.dateEvent', 'ASC');
        if ($premiumFilter === 'premium') {
            $eventsQb->andWhere('e.isPremium = true');
        } elseif ($premiumFilter === 'standard') {
            $eventsQb->andWhere('e.isPremium = false');
        }
        $events = $eventsQb->getQuery()->getResult();
        $eventIds = array_values(array_filter(array_map(
            static fn (Event $event) => $event->getId(),
            $events
        )));
        $eventStats = $this->buildEventStats($events, $waitlistEntryRepository, $eventCapacityService);
        $participantsByEvent = [];
        if ($this->tableExists($em, 'participation') && $eventIds !== []) {
            $rows = $em->getRepository(Participation::class)
                ->createQueryBuilder('p')
                ->select('IDENTITY(p.event) AS eventId, p.nomParticipant AS name')
                ->where('p.event IN (:ids)')
                ->setParameter('ids', $eventIds)
                ->orderBy('p.nomParticipant', 'ASC')
                ->getQuery()
                ->getArrayResult();
            foreach ($rows as $row) {
                $eventId = (int) $row['eventId'];
                $name = trim((string) $row['name']);
                if ($name === '') {
                    continue;
                }
                $participantsByEvent[$eventId][] = $name;
            }
        }
        $participantsList = [];
        if ($this->tableExists($em, 'participation')) {
            $participantsList = $em->getRepository(Participation::class)
                ->findAllWithEventTitlesFiltered($search, $sort);
        }
        $waitlistByEvent = [];
        foreach ($events as $event) {
            if ($event->getId() === null) {
                continue;
            }
            $waitlistByEvent[(int) $event->getId()] = $waitlistEntryRepository->findByEventOrdered($event);
        }

        $totalParticipants = 0;
        $totalFull = 0;
        $totalPremium = 0;
        foreach ($events as $event) {
            $id = (int) $event->getId();
            $activeReservations = $eventStats[$id]['activeReservations'] ?? 0;
            $totalParticipants += $activeReservations;
            if (($eventStats[$id]['isFull'] ?? false) === true) {
                $totalFull++;
            }
            if ($event->isPremium()) {
                $totalPremium++;
            }
        }

        return $this->render('admin/gestion-events.html.twig', [
            'events' => $events,
            'eventStats' => $eventStats,
            'participantsByEvent' => $participantsByEvent,
            'participantsList' => $participantsList,
            'totalEvents' => count($events),
            'totalParticipants' => $totalParticipants,
            'totalFull' => $totalFull,
            'totalPremium' => $totalPremium,
            'search' => $search,
            'sort' => $sort,
            'premiumFilter' => $premiumFilter,
            'waitlistByEvent' => $waitlistByEvent,
        ]);
    }

    #[Route('/waitlist', name: 'waitlist', methods: ['GET'])]
    public function waitlist(
        Request $request,
        EntityManagerInterface $em,
        WaitlistEntryRepository $waitlistEntryRepository,
        EventCapacityService $eventCapacityService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $events = $em->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->orderBy('e.dateEvent', 'ASC')
            ->getQuery()
            ->getResult();

        $selectedEventId = $request->query->getInt('eventId');
        $selectedEvent = null;
        foreach ($events as $event) {
            if (!$event instanceof Event || $event->getId() === null) {
                continue;
            }
            if ($selectedEventId > 0 && (int) $event->getId() === $selectedEventId) {
                $selectedEvent = $event;
                break;
            }
        }
        if (!$selectedEvent instanceof Event && isset($events[0]) && $events[0] instanceof Event) {
            $selectedEvent = $events[0];
        }

        $entries = [];
        $pendingCount = 0;
        $inviteCount = 0;
        $remainingPlaces = null;
        if ($selectedEvent instanceof Event) {
            $entries = $waitlistEntryRepository->findByEventOrdered($selectedEvent);
            $pendingCount = $waitlistEntryRepository->countByEventAndStatus($selectedEvent, WaitlistEntry::STATUS_EN_ATTENTE);
            $inviteCount = $waitlistEntryRepository->countByEventAndStatus($selectedEvent, WaitlistEntry::STATUS_INVITE);
            $remainingPlaces = $eventCapacityService->remainingPlaces($selectedEvent);
        }

        return $this->render('admin/waitlist.html.twig', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'entries' => $entries,
            'pendingCount' => $pendingCount,
            'inviteCount' => $inviteCount,
            'remainingPlaces' => $remainingPlaces,
            'latestPromotions' => $waitlistEntryRepository->findLatestPromotions(10),
        ]);
    }

    #[Route('/events/add', name: 'events_add', methods: ['POST'])]
    public function eventsAdd(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_events');
        }

        if (!$this->tableExists($em, 'events')) {
            $this->addFlash('error', 'Events table is missing. Import/migrate events schema first.');
            return $this->redirectToRoute('admin_events');
        }

        $validation = $this->validateEventPayload($request);
        if ($validation['error']) {
            $this->addFlash('error', $validation['error']);
            return $this->redirectToRoute('admin_events');
        }

        $event = new Event();
        $event->setTitre($validation['data']['titre']);
        $event->setDescription($validation['data']['description']);
        $event->setDateEvent($validation['data']['dateEvent']);
        $event->setLieu($validation['data']['lieu']);
        $event->setCapacite($validation['data']['capacite']);
        $event->setTypeEvent($validation['data']['typeEvent']);
        $event->setPrixEvent($validation['data']['prixEvent']);
        $event->setIsPremium($validation['data']['isPremium']);
        $event->setCreatedAt(new \DateTimeImmutable());

        $uploadedImage = $this->handleEventImageUpload($request->files->get('image_file'), $slugger);
        if ($uploadedImage['error']) {
            $this->addFlash('error', $uploadedImage['error']);
            return $this->redirectToRoute('admin_events');
        }
        if ($uploadedImage['filename']) {
            $event->setImageEvent($uploadedImage['filename']);
        }

        $em->persist($event);
        $em->flush();
        $this->addFlash('success', 'Event created successfully.');

        return $this->redirectToRoute('admin_events');
    }

    #[Route('/events/{id}/update', name: 'events_update', methods: ['POST'])]
    public function eventsUpdate(Event $event, Request $request, EntityManagerInterface $em, SluggerInterface $slugger, WaitlistService $waitlistService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_update_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_events');
        }

        $validation = $this->validateEventPayload($request);
        if ($validation['error']) {
            $this->addFlash('error', $validation['error']);
            return $this->redirectToRoute('admin_events');
        }

        $oldCapacity = $event->getCapacite();
        $event->setTitre($validation['data']['titre']);
        $event->setDescription($validation['data']['description']);
        $event->setDateEvent($validation['data']['dateEvent']);
        $event->setLieu($validation['data']['lieu']);
        $event->setCapacite($validation['data']['capacite']);
        $event->setTypeEvent($validation['data']['typeEvent']);
        $event->setPrixEvent($validation['data']['prixEvent']);
        $event->setIsPremium($validation['data']['isPremium']);

        $uploadedImage = $this->handleEventImageUpload($request->files->get('image_file'), $slugger);
        if ($uploadedImage['error']) {
            $this->addFlash('error', $uploadedImage['error']);
            return $this->redirectToRoute('admin_events');
        }
        if ($uploadedImage['filename']) {
            $event->setImageEvent($uploadedImage['filename']);
        }

        $em->flush();
        if ($event->getCapacite() > $oldCapacity) {
            $delta = $event->getCapacite() - $oldCapacity;
            $invitedCount = 0;
            for ($i = 0; $i < $delta; $i++) {
                $entry = $waitlistService->processNextInvite($event);
                if (!$entry instanceof WaitlistEntry) {
                    break;
                }
                $invitedCount++;
            }
            if ($invitedCount > 0) {
                $this->addFlash('info', sprintf('%d invitation(s) waitlist envoyee(s) apres augmentation capacite.', $invitedCount));
            }
        }
        $this->addFlash('success', 'Event updated successfully.');

        return $this->redirectToRoute('admin_events');
    }

    #[Route('/events/{id}/waitlist/invite-next', name: 'events_waitlist_invite_next', methods: ['POST'])]
    public function eventsWaitlistInviteNext(
        Event $event,
        Request $request,
        WaitlistService $waitlistService,
        EventCapacityService $eventCapacityService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_waitlist_invite_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToAdminWaitlistOrEvents($request, $event);
        }

        if ($eventCapacityService->remainingPlaces($event) <= 0) {
            $this->addFlash('error', 'Place disponible mais event deja complet (si erreur).');
            return $this->redirectToAdminWaitlistOrEvents($request, $event);
        }

        $entry = $waitlistService->processNextInvite($event);
        if ($entry instanceof WaitlistEntry) {
            $this->addFlash('success', 'Place liberee: '.$entry->getEmail().' a ete invite depuis la liste d attente.');
        } else {
            $this->addFlash('info', 'Aucune entree en attente.');
        }

        return $this->redirectToAdminWaitlistOrEvents($request, $event);
    }

    #[Route('/waitlist/{id}/cancel', name: 'events_waitlist_cancel', methods: ['POST'])]
    public function eventsWaitlistCancel(
        WaitlistEntry $entry,
        Request $request,
        EntityManagerInterface $em,
        WaitlistService $waitlistService,
        EventCapacityService $eventCapacityService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_waitlist_cancel_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToAdminWaitlistOrEvents($request, $entry->getEvent());
        }

        $event = $entry->getEvent();
        $entry->setStatus(WaitlistEntry::STATUS_ANNULEE);
        $entry->setToken(null);
        $entry->setInvitedAt(null);
        $entry->setExpiresAt(null);
        $em->flush();

        if ($event instanceof Event && $eventCapacityService->remainingPlaces($event) > 0) {
            $invited = $waitlistService->processNextInvite($event);
            if ($invited instanceof WaitlistEntry) {
                $this->addFlash('success', 'Place liberee: '.$invited->getEmail().' a ete invite depuis la liste d attente.');
            } else {
                $this->addFlash('info', 'Aucune entree en attente.');
            }
        }

        $this->addFlash('success', 'Entree waitlist annulee.');
        return $this->redirectToAdminWaitlistOrEvents($request, $event);
    }

    #[Route('/events/{id}/waitlist/expire', name: 'events_waitlist_expire', methods: ['POST'])]
    public function eventsWaitlistExpire(
        Event $event,
        Request $request,
        WaitlistService $waitlistService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_waitlist_expire_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToAdminWaitlistOrEvents($request, $event);
        }

        $expiredCount = $waitlistService->expireInvitesForEvent($event);
        if ($expiredCount > 0) {
            $this->addFlash('success', sprintf('%d entree(s) waitlist expiree(s).', $expiredCount));
        } else {
            $this->addFlash('info', 'Aucune entree expiree a nettoyer.');
        }

        return $this->redirectToAdminWaitlistOrEvents($request, $event);
    }

    #[Route('/events/{id}/delete', name: 'events_delete', methods: ['POST'])]
    public function eventsDelete(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_delete_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_events');
        }

        $em->remove($event);
        $em->flush();
        $this->addFlash('success', 'Event deleted successfully.');

        return $this->redirectToRoute('admin_events');
    }

    #[Route('/trainings', name: 'trainings')]
    public function trainings(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $tablesReady = $this->tableExists($em, 'fitness_program')
            && $this->tableExists($em, 'fitness_exercise')
            && $this->tableExists($em, 'fitness_program_exercise');
        $trendsReady = $this->tableExists($em, 'fitness_trend');

        $users = $em->getRepository(User::class)->findBy([], ['id' => 'ASC']);
        $plans = [];
        $programs = [];
        $trends = [];

        if ($tablesReady) {
            $plans = $em->getRepository(FitnessProgram::class)
                ->createQueryBuilder('p')
                ->leftJoin('p.exercises', 'ex')
                ->addSelect('ex')
                ->leftJoin('p.user', 'pu')
                ->addSelect('pu')
                ->orderBy('p.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();

            $programs = $em->getRepository(FitnessExercise::class)
                ->createQueryBuilder('e')
                ->leftJoin('e.programs', 'pl')
                ->addSelect('pl')
                ->leftJoin('e.user', 'eu')
                ->addSelect('eu')
                ->orderBy('e.updatedAt', 'DESC')
                ->getQuery()
                ->getResult();
        }
        if ($trendsReady) {
            $trends = $em->getRepository(FitnessTrend::class)->findBy([], ['updatedAt' => 'DESC']);
        }

        return $this->render('admin/gestion-trainings.html.twig', [
            'tablesReady' => $tablesReady,
            'users' => $users,
            'plans' => $plans,
            'programs' => $programs,
            'trends' => $trends,
        ]);
    }

    #[Route('/trainings/plan/add', name: 'trainings_plan_add', methods: ['POST'])]
    public function trainingsPlanAdd(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_plan_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $title = trim((string) $request->request->get('title'));
        $category = trim((string) $request->request->get('category'));
        $level = trim((string) $request->request->get('level'));
        if ($title === '' || $category === '' || $level === '') {
            $this->addFlash('error', 'Plan title, category and level are required.');
            return $this->redirectToRoute('admin_trainings');
        }

        $plan = new FitnessProgram();
        $plan->setTitle($title);
        $plan->setCategory($category);
        $plan->setLevel($level);
        $plan->setDescription(trim((string) $request->request->get('description')) ?: null);
        $plan->setDurationWeeks(max(1, $request->request->getInt('duration_weeks', 4)));
        $plan->setSessionsPerWeek(max(1, $request->request->getInt('sessions_per_week', 3)));
        $plan->setSessionDuration(max(1, $request->request->getInt('session_duration', 45)));
        $plan->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
        $plan->setVideoUrl(trim((string) $request->request->get('video_url')) ?: null);
        $plan->setIsPublic((string) $request->request->get('is_public', '1') !== '0');

        $owner = $em->getRepository(User::class)->find($request->request->getInt('user_id'));
        $plan->setUser($owner);

        $selectedPrograms = $request->request->all('program_ids');
        foreach ($selectedPrograms as $programId) {
            $program = $em->getRepository(FitnessExercise::class)->find((int) $programId);
            if ($program) {
                $plan->addExercise($program);
            }
        }

        $em->persist($plan);
        $em->flush();
        $this->addFlash('success', 'Training plan created successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/plan/{id}/update', name: 'trainings_plan_update', methods: ['POST'])]
    public function trainingsPlanUpdate(FitnessProgram $plan, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_plan_update_'.$plan->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $title = trim((string) $request->request->get('title'));
        $category = trim((string) $request->request->get('category'));
        $level = trim((string) $request->request->get('level'));
        if ($title === '' || $category === '' || $level === '') {
            $this->addFlash('error', 'Plan title, category and level are required.');
            return $this->redirectToRoute('admin_trainings');
        }

        $plan->setTitle($title);
        $plan->setCategory($category);
        $plan->setLevel($level);
        $plan->setDescription(trim((string) $request->request->get('description')) ?: null);
        $plan->setDurationWeeks(max(1, $request->request->getInt('duration_weeks', 4)));
        $plan->setSessionsPerWeek(max(1, $request->request->getInt('sessions_per_week', 3)));
        $plan->setSessionDuration(max(1, $request->request->getInt('session_duration', 45)));
        $plan->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
        $plan->setVideoUrl(trim((string) $request->request->get('video_url')) ?: null);
        $plan->setIsPublic((string) $request->request->get('is_public', '1') !== '0');
        $plan->setUser($em->getRepository(User::class)->find($request->request->getInt('user_id')));

        foreach ($plan->getExercises()->toArray() as $existing) {
            $plan->removeExercise($existing);
        }
        $selectedPrograms = $request->request->all('program_ids');
        foreach ($selectedPrograms as $programId) {
            $program = $em->getRepository(FitnessExercise::class)->find((int) $programId);
            if ($program) {
                $plan->addExercise($program);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Training plan updated successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/plan/{id}/delete', name: 'trainings_plan_delete', methods: ['POST'])]
    public function trainingsPlanDelete(FitnessProgram $plan, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_plan_delete_'.$plan->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $em->remove($plan);
        $em->flush();
        $this->addFlash('success', 'Training plan deleted successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/program/add', name: 'trainings_program_add', methods: ['POST'])]
    public function trainingsProgramAdd(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_program_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $name = trim((string) $request->request->get('name'));
        $muscleGroup = trim((string) $request->request->get('muscle_group'));
        $difficulty = trim((string) $request->request->get('difficulty'));
        if ($name === '' || $muscleGroup === '' || $difficulty === '') {
            $this->addFlash('error', 'Program name, muscle group and difficulty are required.');
            return $this->redirectToRoute('admin_trainings');
        }

        $program = new FitnessExercise();
        $program->setName($name);
        $program->setMuscleGroup($muscleGroup);
        $program->setDifficulty($difficulty);
        $program->setDescription(trim((string) $request->request->get('description')) ?: null);
        $program->setSets($request->request->getInt('sets') ?: null);
        $program->setRepetitions($request->request->getInt('repetitions') ?: null);
        $program->setDuration($request->request->getInt('duration') ?: null);
        $program->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
        $program->setVideoUrl(trim((string) $request->request->get('video_url')) ?: null);
        $program->setUser($em->getRepository(User::class)->find($request->request->getInt('user_id')));

        $linkedPlans = $request->request->all('plan_ids');
        foreach ($linkedPlans as $planId) {
            $plan = $em->getRepository(FitnessProgram::class)->find((int) $planId);
            if ($plan) {
                $program->addProgram($plan);
                $plan->addExercise($program);
            }
        }

        $em->persist($program);
        $em->flush();
        $this->addFlash('success', 'Program created successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/program/{id}/update', name: 'trainings_program_update', methods: ['POST'])]
    public function trainingsProgramUpdate(FitnessExercise $program, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_program_update_'.$program->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $name = trim((string) $request->request->get('name'));
        $muscleGroup = trim((string) $request->request->get('muscle_group'));
        $difficulty = trim((string) $request->request->get('difficulty'));
        if ($name === '' || $muscleGroup === '' || $difficulty === '') {
            $this->addFlash('error', 'Program name, muscle group and difficulty are required.');
            return $this->redirectToRoute('admin_trainings');
        }

        $program->setName($name);
        $program->setMuscleGroup($muscleGroup);
        $program->setDifficulty($difficulty);
        $program->setDescription(trim((string) $request->request->get('description')) ?: null);
        $program->setSets($request->request->getInt('sets') ?: null);
        $program->setRepetitions($request->request->getInt('repetitions') ?: null);
        $program->setDuration($request->request->getInt('duration') ?: null);
        $program->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
        $program->setVideoUrl(trim((string) $request->request->get('video_url')) ?: null);
        $program->setUser($em->getRepository(User::class)->find($request->request->getInt('user_id')));

        foreach ($program->getPrograms()->toArray() as $existingPlan) {
            $program->removeProgram($existingPlan);
            $existingPlan->removeExercise($program);
        }
        $linkedPlans = $request->request->all('plan_ids');
        foreach ($linkedPlans as $planId) {
            $plan = $em->getRepository(FitnessProgram::class)->find((int) $planId);
            if ($plan) {
                $program->addProgram($plan);
                $plan->addExercise($program);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Program updated successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/program/{id}/delete', name: 'trainings_program_delete', methods: ['POST'])]
    public function trainingsProgramDelete(FitnessExercise $program, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_program_delete_'.$program->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $em->remove($program);
        $em->flush();
        $this->addFlash('success', 'Program deleted successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/trend/add', name: 'trainings_trend_add', methods: ['POST'])]
    public function trainingsTrendAdd(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_trend_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $title = trim((string) $request->request->get('title'));
        $category = trim((string) $request->request->get('category'));
        if ($title === '' || $category === '') {
            $this->addFlash('error', 'Trend title and category are required.');
            return $this->redirectToRoute('admin_trainings');
        }

        $trend = new FitnessTrend();
        $trend->setTitle($title);
        $trend->setCategory($category);
        $trend->setDescription(trim((string) $request->request->get('description')) ?: null);
        $trend->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
        $trend->setIsActive((string) $request->request->get('is_active', '1') !== '0');

        $em->persist($trend);
        $em->flush();
        $this->addFlash('success', 'Trend created successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/trend/{id}/update', name: 'trainings_trend_update', methods: ['POST'])]
    public function trainingsTrendUpdate(FitnessTrend $trend, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_trend_update_'.$trend->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $title = trim((string) $request->request->get('title'));
        $category = trim((string) $request->request->get('category'));
        if ($title === '' || $category === '') {
            $this->addFlash('error', 'Trend title and category are required.');
            return $this->redirectToRoute('admin_trainings');
        }

        $trend->setTitle($title);
        $trend->setCategory($category);
        $trend->setDescription(trim((string) $request->request->get('description')) ?: null);
        $trend->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
        $trend->setIsActive((string) $request->request->get('is_active', '1') !== '0');

        $em->flush();
        $this->addFlash('success', 'Trend updated successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/trend/{id}/delete', name: 'trainings_trend_delete', methods: ['POST'])]
    public function trainingsTrendDelete(FitnessTrend $trend, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_trend_delete_'.$trend->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings');
        }

        $em->remove($trend);
        $em->flush();
        $this->addFlash('success', 'Trend deleted successfully.');

        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/users/add', name: 'users_add', methods: ['POST'])]
    public function addUserAdmin(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_users_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }

        $this->addFlash('info', 'User create route is available. Persistence logic can be added next.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/update', name: 'users_update', methods: ['POST'])]
    public function updateUserAdmin(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_users_update_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }

        $this->addFlash('info', 'User update route is available. Persistence logic can be added next.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/delete', name: 'users_delete', methods: ['POST'])]
    public function deleteUserAdmin(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_users_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }

        $this->addFlash('info', 'User delete route is available. Persistence logic can be added next.');
        return $this->redirectToRoute('admin_users');
    }

    #[Route('/nutrition', name: 'nutrition')]
    public function nutrition(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $repas = new Repas();
        $form = $this->createForm(RepasFormType::class, $repas, [
            'attr' => ['id' => 'repas-create-form'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($repas);
            $em->flush();
            $this->addFlash('success', 'Repas crÃ©Ã© avec succÃ¨s.');
            return $this->redirectToRoute('admin_nutrition', ['tab' => 'explore']);
        }

        $repasList = $em->getRepository(Repas::class)->findBy([], ['dateRepas' => 'DESC']);
        $items = [];
        $editForms = [];
        foreach ($repasList as $r) {
            $items[] = [
                'id' => $r->getId(),
                'nomRepas' => $r->getNomRepas(),
                'typeRepas' => $r->getTypeRepas(),
                'date' => $r->getDateRepas()->format('Y-m-d H:i'),
                'dateRaw' => $r->getDateRepas()->format('Y-m-d H:i:s'),
                'email' => $r->getUser()?->getEmail() ?? '',
                'calories' => $r->getCalories(),
            ];

            $editForms[$r->getId()] = $this->createForm(RepasFormType::class, $r, [
                'action' => $this->generateUrl('admin_nutrition_repas_edit', ['id' => $r->getId()]),
                'attr' => ['class' => 'repas-edit-form', 'data-repas-id' => $r->getId()],
            ])->createView();
        }

        return $this->render('admin/gestion-nutrition.html.twig', [
            'repasForm' => $form->createView(),
            'items' => $items,
            'editForms' => $editForms,
            'currentTab' => (string) $request->query->get('tab', 'create'),
        ]);
    }

    #[Route('/nutrition/repas/{id}/edit', name: 'nutrition_repas_edit', methods: ['GET', 'POST'])]
    public function editRepas(Repas $repas, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('admin_nutrition');
        }
        $form = $this->createForm(RepasFormType::class, $repas);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Repas mis Ã  jour avec succÃ¨s.');
            return $this->redirectToRoute('admin_nutrition');
        }

        return $this->redirectToRoute('admin_nutrition');
    }

    #[Route('/nutrition/repas/{id}/delete', name: 'nutrition_repas_delete', methods: ['POST'])]
    public function deleteRepas(Repas $repas, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete_repas_'.$repas->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_nutrition');
        }

        $em->remove($repas);
        $em->flush();
        $this->addFlash('success', 'Repas supprimÃ©.');
        return $this->redirectToRoute('admin_nutrition');
    }

    #[Route('/nutrition/repas/{id}/pdf', name: 'nutrition_repas_pdf', methods: ['GET'])]
    public function exportRepasPdf(Repas $repas): Response
    {
        $clean = static function (?string $s): string {
            if ($s === null || $s === '') {
                return '';
            }
            $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

            return $s !== false ? $s : '';
        };

        $repasData = [
            'nomRepas' => $clean($repas->getNomRepas()),
            'dateRepas' => $repas->getDateRepas(),
            'typeRepas' => $clean($repas->getTypeRepas()),
            'email' => $repas->getUser() ? $clean($repas->getUser()->getEmail()) : '-',
            'calories' => $repas->getCalories() ?? 0,
            'proteines' => $repas->getProteines() ?? 0,
            'glucides' => $repas->getGlucides() ?? 0,
            'lipides' => $repas->getLipides() ?? 0,
            'commentaire' => $clean($repas->getCommentaire() ?? ''),
        ];

        $html = $this->renderView('admin/repas_pdf.html.twig', ['repas' => $repasData]);
        $html = @iconv('UTF-8', 'UTF-8//IGNORE', $html) ?: $html;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isFontSubsettingEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'repas-' . $repas->getId() . '-' . preg_replace('/[^a-z0-9-]/i', '-', $repasData['nomRepas']) . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/billing', name: 'billing')]
    public function billing(): Response
    {
        return $this->render('admin/billing.html.twig');
    }

    #[Route('/virtual-reality', name: 'virtual_reality')]
    public function virtualReality(): Response
    {
        return $this->render('admin/virtual-reality.html.twig');
    }

    #[Route('/rtl', name: 'rtl')]
    public function rtl(): Response
    {
        return $this->render('admin/rtl.html.twig');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $avatarFile */
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                try {
                    $avatarFile->move(
                        $this->getParameter('app.avatar_upload_dir'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload avatar. Please try again.');
                }

                $user->setAvatar($newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'Profile updated successfully.');

            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }

    #[Route('/profile/delete', name: 'profile_delete', methods: ['POST'])]
    public function deleteProfile(
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_admin_profile', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_profile');
        }

        $em->remove($user);
        $em->flush();

        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->redirectToRoute('home');
    }

    #[Route('/profile/avatar/generate', name: 'profile_avatar_generate', methods: ['POST'])]
    public function generateProfileAvatar(
        Request $request,
        EntityManagerInterface $em,
        SmartAvatarService $smartAvatarService
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('admin_generate_avatar', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_profile');
        }

        $svg = $smartAvatarService->buildStatusAvatarSvg($user);
        $filename = sprintf('smart-admin-avatar-%d-%s.svg', (int) $user->getId(), substr(sha1((string) microtime(true)), 0, 10));
        $targetDir = (string) $this->getParameter('app.avatar_upload_dir');

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            $this->addFlash('error', 'Could not prepare avatar directory.');
            return $this->redirectToRoute('admin_profile');
        }

        try {
            file_put_contents($targetDir.DIRECTORY_SEPARATOR.$filename, $svg);
            $user->setAvatar($filename);
            $em->flush();
            $this->addFlash('success', 'Personalized avatar generated.');
        } catch (\Throwable) {
            $this->addFlash('error', 'Could not generate avatar. Please try again.');
        }

        return $this->redirectToRoute('admin_profile');
    }

    #[Route('/sign-in', name: 'sign_in')]
    public function signIn(): Response
    {
        return $this->render('admin/sign-in.html.twig');
    }

    #[Route('/sign-up', name: 'sign_up')]
    public function signUp(): Response
    {
        return $this->render('admin/sign-up.html.twig');
    }

    #[Route('/users/pdf', name: 'users_pdf', methods: ['GET'])]
    public function exportUsersPdf(EntityManagerInterface $em): Response
    {
        $users = $em->getRepository(User::class)->findBy([], ['id' => 'DESC']);
        $html = $this->renderView('admin/users_pdf.html.twig', ['users' => $users]);

        return $this->pdfResponse($html, 'admin-users.pdf');
    }

    #[Route('/blog-forum/posts/pdf', name: 'blog_forum_posts_pdf', methods: ['GET'])]
    public function exportBlogPostsPdf(EntityManagerInterface $em): Response
    {
        $posts = $em->getRepository(BlogPost::class)->findBy([], ['createdAt' => 'DESC']);
        $clean = static function (?string $s): string {
            if ($s === null || $s === '') {
                return '';
            }
            $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

            return $s !== false ? $s : '';
        };

        $rows = [];
        foreach ($posts as $post) {
            $rows[] = [
                'id' => $post->getId(),
                'title' => $clean($post->getTitle()),
                'authorEmail' => $post->getAuthor() ? $clean($post->getAuthor()->getEmail()) : '-',
                'category' => $clean($post->getCategory() ?: '-'),
                'status' => $clean($post->getStatus()),
                'createdAt' => $post->getCreatedAt(),
            ];
        }

        $html = $this->renderView('admin/blog_posts_pdf.html.twig', ['posts' => $rows]);

        return $this->pdfResponse($html, 'admin-blog-posts.pdf');
    }

    #[Route('/blog-forum/interactions/pdf', name: 'blog_forum_interactions_pdf', methods: ['GET'])]
    public function exportBlogInteractionsPdf(EntityManagerInterface $em): Response
    {
        $interactions = $em->getRepository(ContentInteraction::class)->findBy([
            'targetType' => 'blog_post',
        ], ['createdAt' => 'DESC']);

        $clean = static function (?string $s): string {
            if ($s === null || $s === '') {
                return '';
            }
            $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);

            return $s !== false ? $s : '';
        };

        $rows = [];
        foreach ($interactions as $interaction) {
            $rows[] = [
                'id' => $interaction->getId(),
                'interactionType' => $clean($interaction->getInteractionType()),
                'userEmail' => $interaction->getUser() ? $clean($interaction->getUser()->getEmail()) : '-',
                'targetId' => $interaction->getTargetId(),
                'commentText' => $clean($interaction->getCommentText() ?: '-'),
                'createdAt' => $interaction->getCreatedAt(),
            ];
        }

        $html = $this->renderView('admin/blog_interactions_pdf.html.twig', ['interactions' => $rows]);

        return $this->pdfResponse($html, 'admin-blog-interactions.pdf');
    }

    #[Route('/stocks/supplements/pdf', name: 'stocks_supplements_pdf', methods: ['GET'])]
    public function exportSupplementsPdf(EntityManagerInterface $em): Response
    {
        $supplements = $em->getRepository(Supplement::class)->findBy([], ['updatedAt' => 'DESC']);
        $html = $this->renderView('admin/stocks_supplements_pdf.html.twig', ['supplements' => $supplements]);

        return $this->pdfResponse($html, 'admin-supplements.pdf');
    }

    #[Route('/stocks/payments/pdf', name: 'stocks_payments_pdf', methods: ['GET'])]
    public function exportPaymentsPdf(EntityManagerInterface $em): Response
    {
        $payments = $em->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC']);
        $html = $this->renderView('admin/stocks_payments_pdf.html.twig', ['payments' => $payments]);

        return $this->pdfResponse($html, 'admin-payments.pdf');
    }

    #[Route('/events/pdf', name: 'events_pdf', methods: ['GET'])]
    public function exportEventsPdf(EntityManagerInterface $em): Response
    {
        $events = $em->getRepository(Event::class)->findBy([], ['dateEvent' => 'DESC']);
        $html = $this->renderView('admin/events_pdf.html.twig', ['events' => $events]);

        return $this->pdfResponse($html, 'admin-events.pdf');
    }

    #[Route('/trainings/plans/pdf', name: 'trainings_plans_pdf', methods: ['GET'])]
    public function exportTrainingPlansPdf(EntityManagerInterface $em): Response
    {
        $plans = $em->getRepository(FitnessProgram::class)->findBy([], ['updatedAt' => 'DESC']);
        $html = $this->renderView('admin/trainings_plans_pdf.html.twig', ['plans' => $plans]);

        return $this->pdfResponse($html, 'admin-training-plans.pdf');
    }

    #[Route('/trainings/programs/pdf', name: 'trainings_programs_pdf', methods: ['GET'])]
    public function exportTrainingProgramsPdf(EntityManagerInterface $em): Response
    {
        $programs = $em->getRepository(FitnessExercise::class)->findBy([], ['updatedAt' => 'DESC']);
        $html = $this->renderView('admin/trainings_programs_pdf.html.twig', ['programs' => $programs]);

        return $this->pdfResponse($html, 'admin-training-programs.pdf');
    }

    private function validateEventPayload(Request $request): array
    {
        $titre = trim((string) $request->request->get('titre'));
        $description = trim((string) $request->request->get('description'));
        $lieu = trim((string) $request->request->get('lieu'));
        $typeEvent = trim((string) $request->request->get('type_event'));
        $dateEvent = trim((string) $request->request->get('date_event'));
        $capacite = $request->request->getInt('capacite');
        $prix = (float) $request->request->get('prix_event', 0);
        $isPremium = in_array((string) $request->request->get('is_premium', '0'), ['1', 'on', 'true'], true);

        if ($titre === '' || $description === '' || $lieu === '' || $typeEvent === '') {
            return ['error' => 'Title, description, location and type are required.', 'data' => null];
        }
        if ($capacite < 1) {
            return ['error' => 'Capacity must be a positive integer.', 'data' => null];
        }
        if ($prix < 0) {
            return ['error' => 'Price must be positive or zero.', 'data' => null];
        }
        try {
            $date = new \DateTimeImmutable($dateEvent);
        } catch (\Exception) {
            return ['error' => 'Invalid event date.', 'data' => null];
        }

        return [
            'error' => null,
            'data' => [
                'titre' => $titre,
                'description' => $description,
                'lieu' => $lieu,
                'typeEvent' => $typeEvent,
                'dateEvent' => $date,
                'capacite' => $capacite,
                'prixEvent' => number_format($prix, 2, '.', ''),
                'isPremium' => $isPremium,
            ],
        ];
    }

    private function handleEventImageUpload(?UploadedFile $imageFile, SluggerInterface $slugger): array
    {
        if (!$imageFile) {
            return ['filename' => null, 'error' => null];
        }
        $size = $imageFile->getSize() ?? 0;
        if ($size > 2 * 1024 * 1024) {
            return ['filename' => null, 'error' => 'Image exceeds maximum size (2 MB).'];
        }
        $mime = $imageFile->getMimeType() ?? '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return ['filename' => null, 'error' => 'Unsupported image format.'];
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = 'event-'.$safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
        $targetDir = $this->getParameter('kernel.project_dir').'/public/uploads/events';

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            return ['filename' => null, 'error' => 'Unable to create event upload directory.'];
        }

        try {
            $imageFile->move($targetDir, $newFilename);
        } catch (FileException) {
            return ['filename' => null, 'error' => 'Unable to upload event image.'];
        }

        return ['filename' => $newFilename, 'error' => null];
    }

    private function redirectToAdminWaitlistOrEvents(Request $request, ?Event $event = null): Response
    {
        $redirect = trim((string) $request->request->get('_redirect', ''));
        if ($redirect === 'waitlist') {
            if ($event instanceof Event && $event->getId() !== null) {
                return $this->redirectToRoute('admin_waitlist', ['eventId' => $event->getId()]);
            }

            return $this->redirectToRoute('admin_waitlist');
        }

        return $this->redirectToRoute('admin_events');
    }

    /**
     * @param Event[] $events
     * @return array<int, array<string, int|bool>>
     */
    private function buildEventStats(array $events, WaitlistEntryRepository $waitlistEntryRepository, EventCapacityService $eventCapacityService): array
    {
        $eventIds = [];
        $stats = [];
        foreach ($events as $event) {
            if (!$event instanceof Event || $event->getId() === null) {
                continue;
            }
            $eventIds[] = (int) $event->getId();
            $activeReservations = $eventCapacityService->countActiveReservations($event);
            $remainingPlaces = max(0, (int) $event->getCapacite() - $activeReservations);
            $stats[(int) $event->getId()] = [
                'participants' => $activeReservations,
                'activeReservations' => $activeReservations,
                'remainingPlaces' => $remainingPlaces,
                'isFull' => $remainingPlaces <= 0,
                'waitlistPending' => 0,
                'waitlistInvited' => 0,
            ];
        }

        $waitlistCounts = $waitlistEntryRepository->getStatusCountsByEventIds($eventIds);
        foreach ($waitlistCounts as $eventId => $statusCounts) {
            if (!isset($stats[$eventId])) {
                continue;
            }
            $stats[$eventId]['waitlistPending'] = (int) ($statusCounts[WaitlistEntry::STATUS_EN_ATTENTE] ?? 0);
            $stats[$eventId]['waitlistInvited'] = (int) ($statusCounts[WaitlistEntry::STATUS_INVITE] ?? 0);
        }

        return $stats;
    }
    private function tableExists(EntityManagerInterface $em, string $tableName): bool
    {
        $connection = $em->getConnection();
        $schemaManager = method_exists($connection, 'createSchemaManager')
            ? $connection->createSchemaManager()
            : $connection->getSchemaManager();

        return $schemaManager->tablesExist([$tableName]);
    }

    private function pdfResponse(string $html, string $filename): Response
    {
        $html = @iconv('UTF-8', 'UTF-8//IGNORE', $html) ?: $html;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isFontSubsettingEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}






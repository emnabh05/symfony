<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
use App\Entity\Reservation;
use App\Entity\Supplement;
use App\Entity\User;
use App\Repository\ParticipationRepository;
use App\Service\LoyaltyService;
use App\Service\OrderStatusNotifier;
use App\Service\LegacyUserBridgeService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use App\Entity\Participation;
use App\Entity\WaitlistEntry;
use App\Repository\WaitlistEntryRepository;
use App\Service\EventCapacityService;
use App\Service\WaitlistService;

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

    #[Route('/sign-in', name: 'sign_in')]
    public function signInPage(): Response
    {
        return $this->render('admin/sign-in.html.twig');
    }

    #[Route('/sign-up', name: 'sign_up')]
    public function signUpPage(): Response
    {
        return $this->render('admin/sign-up.html.twig');
    }

    #[Route('/billing', name: 'billing')]
    public function billing(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/billing.html.twig');
    }

    #[Route('/virtual-reality', name: 'virtual_reality')]
    public function virtualReality(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/virtual-reality.html.twig');
    }

    #[Route('/rtl', name: 'rtl')]
    public function rtl(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/rtl.html.twig');
    }

    #[Route('/tables', name: 'tables')]
    public function tables(): Response
    {
        return $this->render('admin/tables.html.twig');
    }

    #[Route('/users', name: 'users')]
    public function users(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $userRepository = $em->getRepository(User::class);
        $allUsers = $userRepository->findBy([], ['id' => 'DESC']);

        return $this->render('admin/gestion-users.html.twig', [
            'managedUsers' => $allUsers,
            'usersSearchQuery' => $search,
            'totalUsersCount' => count($allUsers),
            'adminUsersCount' => count(array_filter(
                $allUsers,
                static fn (User $user): bool => in_array('ROLE_ADMIN', $user->getRoles(), true)
            )),
            'archivedUsersCount' => count(array_filter(
                $allUsers,
                static fn (User $user): bool => $user->isArchived()
            )),
        ]);
    }

    #[Route('/users/add', name: 'users_add', methods: ['POST'])]
    public function addUserAdmin(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('admin_users_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }

        $user = new User();
        $user->setEmail((string) $request->request->get('email'));
        $user->setUsername((string) $request->request->get('username'));
        $user->setFirstName((string) $request->request->get('first_name'));
        $user->setLastName((string) $request->request->get('last_name'));
        $user->setPhone((string) $request->request->get('phone'));
        $user->setRoles([(string) $request->request->get('role', 'ROLE_USER')]);
        
        $plainPassword = (string) $request->request->get('password');
        if ($plainPassword !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        } else {
            $user->setPassword($passwordHasher->hashPassword($user, 'Fitopia2026!'));
        }

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'User created successfully.');
        $search = trim((string) $request->request->get('return_query', ''));

        return $this->redirectToRoute('admin_users', $search !== '' ? ['q' => $search] : []);
    }

    #[Route('/users/{id}/update', name: 'users_update', methods: ['POST'])]
    public function updateUserAdmin(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('admin_users_update_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setEmail((string) $request->request->get('email'));
        $user->setUsername((string) $request->request->get('username'));
        $user->setFirstName((string) $request->request->get('first_name'));
        $user->setLastName((string) $request->request->get('last_name'));
        $user->setPhone((string) $request->request->get('phone'));
        $user->setRoles([(string) $request->request->get('role', 'ROLE_USER')]);

        $plainPassword = (string) $request->request->get('password');
        if ($plainPassword !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        }

        $em->flush();

        $this->addFlash('success', 'User updated successfully.');
        $search = trim((string) $request->request->get('return_query', ''));

        return $this->redirectToRoute('admin_users', $search !== '' ? ['q' => $search] : []);
    }

    #[Route('/users/{id}/delete', name: 'users_delete', methods: ['POST'])]
    public function deleteUserAdmin(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_users_delete_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'User deleted successfully.');
        $search = trim((string) $request->request->get('return_query', ''));

        return $this->redirectToRoute('admin_users', $search !== '' ? ['q' => $search] : []);
    }

    #[Route('/blog-forum', name: 'blog_forum')]
    public function blogForum(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$this->safeUploadedFileExtension($featuredImage, 'jpg');

                try {
                    $uploadDir = $this->getParameter('app.blog_upload_dir');
                    if (!is_string($uploadDir)) {
                        throw new \RuntimeException('Invalid blog upload directory.');
                    }
                    $featuredImage->move($uploadDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload the image. Please try again.');
                }

                $post->setImagePath($newFilename);
            }

            $post->setAuthor($user);
            $post->setCreatedAt(new \DateTimeImmutable());

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Blog post published successfully.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $posts = $em->getRepository(BlogPost::class)->findBy([], ['createdAt' => 'DESC']);
        $allInteractions = $this->fetchForumInteractions($em);
        $managedUsers = $em->getRepository(User::class)->findBy([], ['id' => 'DESC']);

        $interactionStats = [];
        $interactions = [];
        foreach ($allInteractions as $interaction) {
            $postId = (int) ($interaction['postId'] ?? 0);
            if (!isset($interactionStats[$postId])) {
                $interactionStats[$postId] = [
                    'like' => 0,
                    'repost' => 0,
                    'comment' => 0,
                ];
            }

            $type = (string) ($interaction['type'] ?? '');
            if (isset($interactionStats[$postId][$type])) {
                $interactionStats[$postId][$type]++;
            }

            $interactions[] = $interaction;
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

        $userEmailById = [];
        $userNameById = [];
        foreach ($managedUsers as $u) {
            $userEmailById[$u->getId()] = $u->getEmail() ?? ('User #'.$u->getId());
            $fullName = trim(((string) ($u->getFirstName() ?? '')).' '.((string) ($u->getLastName() ?? '')));
            $userNameById[$u->getId()] = $fullName !== '' ? $fullName : ($u->getUsername() ?: ($u->getEmail() ?? ('User #'.$u->getId())));
        }

        foreach ($interactions as $k => $row) {
            $postId = (int) ($row['postId'] ?? 0);
            $userId = (int) ($row['userId'] ?? 0);
            $type = (string) ($row['type'] ?? '');

            $interactions[$k]['postId'] = $postId;
            $interactions[$k]['userId'] = $userId;
            $interactions[$k]['postTitle'] = $postTitleById[$postId] ?? ('Post #'.$postId);
            $interactions[$k]['userEmail'] = $userEmailById[$userId] ?? ('User #'.$userId);
            $interactions[$k]['userName'] = $userNameById[$userId] ?? ($userEmailById[$userId] ?? ('User #'.$userId));
            $interactions[$k]['comment'] = $type === 'comment' ? ($row['content'] ?? null) : null;
            $interactions[$k]['interactionTable'] = $this->forumInteractionTableForType($type) ?? '';
            $interactions[$k]['date'] = isset($row['date']) && $row['date'] ? (string) $row['date'] : '-';
            $interactions[$k]['actionLabel'] = match ($type) {
                'like' => 'Liked this post',
                'repost' => 'Reposted this post',
                'comment' => 'Commented on this post',
                default => 'Interacted with this post',
            };
            $interactions[$k]['typeLabel'] = strtoupper($type);

            try {
                $displayDate = new \DateTimeImmutable((string) $interactions[$k]['date']);
                $monthMap = [
                    'Jan' => 'jan', 'Feb' => 'fev', 'Mar' => 'mar', 'Apr' => 'avr',
                    'May' => 'mai', 'Jun' => 'juin', 'Jul' => 'juil', 'Aug' => 'aou',
                    'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dec',
                ];
                $monthShort = $monthMap[$displayDate->format('M')] ?? strtolower($displayDate->format('M'));
                $interactions[$k]['displayDate'] = $displayDate->format('d').' '.$monthShort.', '.$displayDate->format('H:i');
                $interactions[$k]['timestamp'] = $displayDate->getTimestamp();
            } catch (\Throwable) {
                $interactions[$k]['displayDate'] = (string) $interactions[$k]['date'];
                $interactions[$k]['timestamp'] = 0;
            }
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
            $role = $p->getAuthor()?->getRole() ?? 'Patient';
            $stats = $interactionStats[$p->getId()] ?? ['like' => 0, 'repost' => 0, 'comment' => 0];
            $items[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'date' => $p->getCreatedAt()->format('Y-m-d'),
                'email' => $p->getAuthor()?->getEmail() ?? '',
                'role' => $role,
                'category' => 'Forum',
                'status' => 'published',
                'likes' => $stats['like'],
                'reposts' => $stats['repost'],
                'comments' => $stats['comment'],
                'excerpt' => $p->getExcerpt() ?? mb_strimwidth(trim(strip_tags($p->getContent())), 0, 120, '...'),
                'image' => $this->normalizeForumImage($p->getFeaturedImage()),
            ];

            $editForms[$p->getId()] = $this->createForm(BlogPostType::class, $p, [
                'action' => $this->generateUrl('admin_blog_forum_edit', ['id' => $p->getId()]),
                'attr' => ['class' => 'blog-edit-form', 'data-post-id' => $p->getId()],
            ])->createView();
        }

        $likesCount = count(array_filter(
            $interactions,
            static fn (array $interaction): bool => ($interaction['type'] ?? '') === 'like'
        ));
        $commentsCount = count(array_filter(
            $interactions,
            static fn (array $interaction): bool => ($interaction['type'] ?? '') === 'comment'
        ));
        $repostsCount = count(array_filter(
            $interactions,
            static fn (array $interaction): bool => ($interaction['type'] ?? '') === 'repost'
        ));

        $categoryCounts = [];
        $activeAuthors = [];
        foreach ($items as $item) {
            $category = (string) ($item['category'] ?? 'Uncategorized');
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

            $authorEmail = trim((string) ($item['email'] ?? ''));
            if ($authorEmail !== '') {
                $activeAuthors[$authorEmail] = true;
            }
        }
        arsort($categoryCounts);
        $topCategory = $categoryCounts !== [] ? (string) array_key_first($categoryCounts) : '-';

        $now = new \DateTimeImmutable('now');
        $weeklySeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->modify('-'.$i.' days');
            $key = $date->format('Y-m-d');
            $weeklySeries[$key] = [
                'label' => strtoupper($date->format('D')),
                'count' => 0,
            ];
        }

        foreach ($posts as $postItem) {
            $createdAt = $postItem->getCreatedAt();
            if (!$createdAt instanceof \DateTimeInterface) {
                continue;
            }

            $key = $createdAt->format('Y-m-d');
            if (isset($weeklySeries[$key])) {
                $weeklySeries[$key]['count']++;
            }
        }

        $weeklyMax = 0;
        $weeklyPosts = 0;
        foreach ($weeklySeries as $point) {
            if ($point['count'] > $weeklyMax) {
                $weeklyMax = $point['count'];
            }
            $weeklyPosts += $point['count'];
        }

        usort($interactions, static function (array $a, array $b): int {
            return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
        });

        $latestInteractions = array_slice($interactions, 0, 5);

        $blogStats = [
            'totalPosts' => count($items),
            'publishedPosts' => count($items),
            'totalInteractions' => count($interactions),
            'comments' => $commentsCount,
            'likes' => $likesCount,
            'reposts' => $repostsCount,
            'engagementPerPost' => count($items) > 0 ? number_format(count($interactions) / count($items), 1, '.', '') : '0.0',
            'activeAuthors' => count($activeAuthors),
            'topCategory' => $topCategory,
            'draftPosts' => 0,
            'weeklySeries' => array_values($weeklySeries),
            'weeklyMax' => max($weeklyMax, 1),
            'weeklyPosts' => $weeklyPosts,
        ];

        return $this->render('admin/gestion-blog-forum.html.twig', [
            'blogForm' => $form->createView(),
            'items' => $items,
            'editForms' => $editForms,
            'interactions' => $interactions,
            'users' => $usersList,
            'posts' => $postsList,
            'blogStats' => $blogStats,
            'latestInteractions' => $latestInteractions,
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$this->safeUploadedFileExtension($featuredImage, 'jpg');

                try {
                    $uploadDir = $this->getParameter('app.blog_upload_dir');
                    if (!is_string($uploadDir)) {
                        throw new \RuntimeException('Invalid blog upload directory.');
                    }
                    $featuredImage->move($uploadDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Could not upload the featured image. Please try again.');
                }

                $post->setFeaturedImage($newFilename);
            }

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

    #[Route('/blog-forum/posts/pdf', name: 'blog_forum_posts_pdf', methods: ['GET'])]
    public function blogForumPostsPdf(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $posts = $em->getRepository(BlogPost::class)->findBy([], ['createdAt' => 'DESC']);
        $rows = '';
        foreach ($posts as $post) {
            $rows .= sprintf(
                '<tr>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                </tr>',
                htmlspecialchars($post->getTitle(), ENT_QUOTES),
                htmlspecialchars($post->getAuthor()?->getEmail() ?? '-', ENT_QUOTES),
                htmlspecialchars($post->getCreatedAt()?->format('Y-m-d H:i') ?? '-', ENT_QUOTES)
            );
        }

        $html = '<html><body style="font-family: DejaVu Sans, sans-serif; color:#0f172a;">
            <h1 style="color:#0f7a5f;">Export posts forum/blog</h1>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Titre</th>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Auteur</th>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Date</th>
                    </tr>
                </thead>
                <tbody>'.$rows.'</tbody>
            </table>
        </body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="forum-posts.pdf"',
        ]);
    }

    #[Route('/blog-forum/interactions/pdf', name: 'blog_forum_interactions_pdf', methods: ['GET'])]
    public function blogForumInteractionsPdf(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $interactions = $this->fetchForumInteractions($em);
        $rows = '';
        foreach ($interactions as $interaction) {
            $rows .= sprintf(
                '<tr>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                    <td style="padding:8px;border:1px solid #d8e4df;">%s</td>
                </tr>',
                htmlspecialchars((string) ($interaction['type'] ?? '-'), ENT_QUOTES),
                htmlspecialchars((string) ($interaction['userId'] ?? '-'), ENT_QUOTES),
                htmlspecialchars((string) ($interaction['postId'] ?? '-'), ENT_QUOTES),
                htmlspecialchars((string) ($interaction['commentText'] ?? '-'), ENT_QUOTES)
            );
        }

        $html = '<html><body style="font-family: DejaVu Sans, sans-serif; color:#0f172a;">
            <h1 style="color:#0f7a5f;">Export interactions forum/blog</h1>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Type</th>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Utilisateur</th>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Post</th>
                        <th style="padding:8px;border:1px solid #d8e4df;background:#eefbf6;">Commentaire</th>
                    </tr>
                </thead>
                <tbody>'.$rows.'</tbody>
            </table>
        </body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="forum-interactions.pdf"',
        ]);
    }

    #[Route('/blog-forum/interactions/add', name: 'blog_forum_interaction_add', methods: ['POST'])]
    public function addBlogInteraction(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_blog_interaction_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $table = $this->forumInteractionTableForType((string) $request->request->get('interaction_type'));
        $userId = $request->request->getInt('user_id');
        $postId = $request->request->getInt('post_id');
        $commentText = trim((string) $request->request->get('comment_text'));

        if ($table === null || $userId <= 0 || $postId <= 0) {
            $this->addFlash('error', 'Invalid interaction payload.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        try {
            $connection = $this->container->get('doctrine')->getConnection();
            if ($table === 'forum_comments') {
                if ($commentText === '') {
                    $this->addFlash('error', 'Comment text is required.');
                    return $this->redirectToRoute('admin_blog_forum');
                }
                $connection->insert('forum_comments', [
                    'user_id' => $userId,
                    'forum_id' => $postId,
                    'content' => $commentText,
                ]);
            } else {
                $exists = $connection->fetchOne(
                    sprintf('SELECT id FROM %s WHERE user_id = :userId AND forum_id = :postId LIMIT 1', $table),
                    ['userId' => $userId, 'postId' => $postId]
                );
                if ($exists === false) {
                    $connection->insert($table, [
                        'user_id' => $userId,
                        'forum_id' => $postId,
                    ]);
                }
            }
            $this->addFlash('success', 'Interaction created.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Could not create interaction: '.$exception->getMessage());
        }

        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/blog-forum/interactions/{id}/update', name: 'blog_forum_interaction_update', methods: ['POST'])]
    public function updateBlogInteraction(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_blog_interaction_update_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $table = (string) $request->request->get('interaction_table');
        $userId = $request->request->getInt('user_id');
        $postId = $request->request->getInt('post_id');
        $commentText = trim((string) $request->request->get('comment_text'));

        if (!in_array($table, ['forum_comments', 'forum_likes', 'forum_reposts'], true) || $userId <= 0 || $postId <= 0) {
            $this->addFlash('error', 'Invalid interaction payload.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        try {
            $connection = $this->container->get('doctrine')->getConnection();
            $data = [
                'user_id' => $userId,
                'forum_id' => $postId,
            ];
            if ($table === 'forum_comments') {
                if ($commentText === '') {
                    $this->addFlash('error', 'Comment text is required.');
                    return $this->redirectToRoute('admin_blog_forum');
                }
                $data['content'] = $commentText;
            }
            $connection->update($table, $data, ['id' => $id]);
            $this->addFlash('success', 'Interaction updated.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Could not update interaction: '.$exception->getMessage());
        }

        return $this->redirectToRoute('admin_blog_forum');
    }

    #[Route('/blog-forum/interactions/{id}/delete', name: 'blog_forum_interaction_delete', methods: ['POST'])]
    public function deleteBlogInteraction(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_blog_interaction_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        $table = (string) $request->request->get('interaction_table');
        if (!in_array($table, ['forum_comments', 'forum_likes', 'forum_reposts'], true)) {
            $this->addFlash('error', 'Invalid interaction target.');
            return $this->redirectToRoute('admin_blog_forum');
        }

        try {
            $connection = $this->container->get('doctrine')->getConnection();
            $connection->delete($table, ['id' => $id]);
            $this->addFlash('success', 'Interaction deleted.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Could not delete interaction: '.$exception->getMessage());
        }

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
                'processing' => 0,
                'on_way' => 0,
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

        if ($this->tableExists($em, 'supplement_orders')) {
            $payments = $em->getRepository(Order::class)->findBy([], ['createdAt' => 'DESC']);

            $recentOrders = $em->getRepository(Order::class)->createQueryBuilder('o')
                ->andWhere('o.createdAt >= :monthStart')
                ->andWhere('o.status NOT IN (:canceledStatuses)')
                ->setParameter('monthStart', $monthStart)
                ->setParameter('canceledStatuses', Order::getCanceledStorageStatuses())
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
                ->andWhere('o.status NOT IN (:canceledStatuses)')
                ->setParameter('canceledStatuses', Order::getCanceledStorageStatuses())
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
                $status = Order::normalizeStatusForDisplay((string) ($row['status'] ?? ''));
                if ($status !== '' && array_key_exists($status, $statusCounts)) {
                    $statusCounts[$status] = (int) $row['count'];
                }
            }
            $stats['statusCounts'] = $statusCounts;
            $stats['totalOrders'] = array_sum($statusCounts);

            if ($this->tableExists($em, 'supplement_order_items')) {
                $itemsSold = $em->createQueryBuilder()
                    ->select('COALESCE(SUM(oi.quantity), 0)')
                    ->from(OrderItem::class, 'oi')
                    ->join('oi.order', 'o')
                    ->andWhere('o.status NOT IN (:canceledStatuses)')
                    ->setParameter('canceledStatuses', Order::getCanceledStorageStatuses())
                    ->getQuery()
                    ->getSingleScalarResult();
                $stats['itemsSold'] = (int) $itemsSold;

                $topProducts = $em->createQueryBuilder()
                    ->select('s.id as id', 's.name as name', 's.brand as brand', 'SUM(oi.quantity) as qty', 'COALESCE(SUM(oi.total), 0) as revenue')
                    ->from(OrderItem::class, 'oi')
                    ->join('oi.order', 'o')
                    ->join('oi.supplement', 's')
                    ->andWhere('o.status NOT IN (:canceledStatuses)')
                    ->andWhere('o.createdAt >= :monthStart')
                    ->setParameter('canceledStatuses', Order::getCanceledStorageStatuses())
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
            $this->addFlash('error', 'Payments table is missing. Import the shared Fitopia schema first.');
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
            $newFilename = $safeFilename.'-'.uniqid().'.'.$this->safeUploadedFileExtension($imageFile, 'jpg');
            try {
                $uploadDir = $this->getParameter('app.supplement_upload_dir');
                if (!is_string($uploadDir)) {
                    throw new \RuntimeException('Invalid supplement upload directory.');
                }
                $imageFile->move($uploadDir, $newFilename);
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
            $newFilename = $safeFilename.'-'.uniqid().'.'.$this->safeUploadedFileExtension($imageFile, 'jpg');
            try {
                $uploadDir = $this->getParameter('app.supplement_upload_dir');
                if (!is_string($uploadDir)) {
                    throw new \RuntimeException('Invalid supplement upload directory.');
                }
                $imageFile->move($uploadDir, $newFilename);
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
        if (!$this->tableExists($em, 'supplement_orders')) {
            $this->addFlash('error', 'Payments table is missing. Run migrations first.');
            return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
        }
        if ($request->isMethod('GET')) {
            return $this->render('admin/stocks_payment_new.html.twig');
        }
        if (!$this->isCsrfTokenValid('admin_stocks_payment_add', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_stocks');
        }

        $order = new Order();
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
        return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
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
        if (!$this->tableExists($em, 'supplement_orders')) {
            $this->addFlash('error', 'Payments table is missing. Run migrations first.');
            return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
        }
        if ($request->isMethod('GET')) {
            return $this->render('admin/stocks_payment_edit.html.twig', [
                'order' => $order,
            ]);
        }
        if (!$this->isCsrfTokenValid('admin_stocks_payment_update_'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
        }

        $oldStatus = (string) $order->getStatus();
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
        return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
    }

    #[Route('/stocks/payment/{id}/delete', name: 'stocks_payment_delete', methods: ['POST'])]
    public function stocksPaymentDelete(Order $order, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->tableExists($em, 'supplement_orders')) {
            $this->addFlash('error', 'Payments table is missing. Run migrations first.');
            return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
        }
        if (!$this->isCsrfTokenValid('admin_stocks_payment_delete_'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
        }

        $em->remove($order);
        $em->flush();
        $this->addFlash('success', 'Payment record deleted.');
        return $this->redirect($this->generateUrl('admin_stocks').'#supplements-orders-section');
    }

    #[Route('/events', name: 'events')]
    public function events(
        Request $request,
        EntityManagerInterface $em,
        WaitlistEntryRepository $waitlistEntryRepository,
        EventCapacityService $eventCapacityService,
        LoyaltyService $loyaltyService
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $activePanel = strtolower(trim((string) $request->query->get('panel', 'explore')));
        if (!in_array($activePanel, ['explore', 'create', 'participants', 'loyalty', 'waitlist'], true)) {
            $activePanel = 'explore';
        }

        $premiumFilter = (string) $request->query->get('premium_filter', 'all');
        if (!in_array($premiumFilter, ['all', 'premium', 'standard'], true)) {
            $premiumFilter = 'all';
        }

        if (!$this->tableExists($em, 'events')) {
            $this->addFlash('error', 'Events table is missing.');
            return $this->redirectToRoute('admin_dashboard');
        }

        $eventsQb = $em->getRepository(Event::class)->createQueryBuilder('e')
            ->orderBy('e.dateEvent', 'ASC');
        if ($premiumFilter === 'premium') {
            $eventsQb->andWhere('e.isPremium = true');
        } elseif ($premiumFilter === 'standard') {
            $eventsQb->andWhere('e.isPremium = false');
        }
        $events = $eventsQb->getQuery()->getResult();
        $eventStats = $this->buildEventStats($events, $waitlistEntryRepository, $eventCapacityService);

        $eventIds = array_values(array_filter(array_map(
            static fn (Event $event): int => (int) ($event->getId() ?? 0),
            $events
        )));
        $waitlistCountsByEvent = $this->tableExists($em, 'waitlist_entry')
            ? $waitlistEntryRepository->getStatusCountsByEventIds($eventIds)
            : [];

        $totalPendingWaitlist = 0;
        $totalInvitedWaitlist = 0;
        foreach ($waitlistCountsByEvent as $counts) {
            $totalPendingWaitlist += (int) ($counts[WaitlistEntry::STATUS_EN_ATTENTE] ?? 0);
            $totalInvitedWaitlist += (int) ($counts[WaitlistEntry::STATUS_INVITE] ?? 0);
        }

        $participantSearch = trim((string) $request->query->get('participant_search', (string) $request->query->get('search', '')));
        $participantEmailFilter = trim((string) $request->query->get('participant_email', ''));
        $participantSort = (string) $request->query->get('participant_sort', 'recent');
        $participantStatusFilter = strtolower(trim((string) $request->query->get('participant_status', 'all')));
        $participantEventFilter = $request->query->getInt('participant_event');
        if (!in_array($participantSort, ['recent', 'oldest'], true)) {
            $participantSort = 'recent';
        }
        if (!in_array($participantStatusFilter, ['all', 'confirmee', 'utilisee', 'annulee'], true)) {
            $participantStatusFilter = 'all';
        }

        $participantsList = [];
        $participantEventOptions = [];
        $participantStatusStats = [
            Reservation::STATUS_CONFIRMED => 0,
            Reservation::STATUS_USED => 0,
            Reservation::STATUS_CANCELLED => 0,
            Reservation::STATUS_PENDING_PAYMENT => 0,
            Reservation::STATUS_PAID => 0,
        ];
        if ($this->tableExists($em, 'participation')) {
            /** @var ParticipationRepository $participationRepository */
            $participationRepository = $em->getRepository(Participation::class);
            $participantsRaw = $participationRepository->findAllWithEventTitlesFiltered($participantSearch, $participantSort);
            $participantMetaMap = $this->buildParticipantReservationMetaMap($em);

            foreach ($participantsRaw as $row) {
                $eventId = (int) ($row['eventId'] ?? 0);
                $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
                $participantMeta = $participantMetaMap[$eventId.'|'.$email] ?? [];
                $status = (string) ($participantMeta['status'] ?? Reservation::STATUS_CONFIRMED);
                $statusSlug = $this->normalizeReservationStatusSlug($status);

                if ($participantEmailFilter !== '' && !str_contains($email, mb_strtolower($participantEmailFilter))) {
                    continue;
                }
                if ($participantEventFilter > 0 && $eventId !== $participantEventFilter) {
                    continue;
                }
                if ($participantStatusFilter !== 'all' && $participantStatusFilter !== $statusSlug) {
                    continue;
                }

                $row['status'] = $status;
                $row['statusSlug'] = $statusSlug;
                $row['phone'] = (string) ($participantMeta['phone'] ?? '-');
                $row['transactionId'] = $participantMeta['transactionId'] ?? null;
                $participantsList[] = $row;
                $participantEventOptions[$eventId] = (string) ($row['eventTitle'] ?? ('Event #'.$eventId));
                if (isset($participantStatusStats[$status])) {
                    $participantStatusStats[$status]++;
                }
            }
        }

        asort($participantEventOptions);

        $loyaltySearch = trim((string) $request->query->get('loyalty_search', ''));
        $loyaltySort = trim((string) $request->query->get('loyalty_sort', 'count_desc'));
        $loyaltyClients = [];
        $vipThreshold = $loyaltyService->getVipReservationThreshold();
        if ($this->tableExists($em, 'reservation')) {
            $reservationRepository = $em->getRepository(Reservation::class);
            $loyaltyRows = method_exists($reservationRepository, 'getLoyaltyLeaderboard')
                ? $reservationRepository->getLoyaltyLeaderboard($loyaltySearch, $loyaltySort)
                : [];

            foreach ($loyaltyRows as $row) {
                $confirmedCount = (int) ($row['countConfirmed'] ?? 0);
                $totalAmount = (float) ($row['totalAmount'] ?? 0);
                $loyaltyClients[] = [
                    'email' => (string) ($row['emailParticipant'] ?? ''),
                    'name' => (string) ($row['nomParticipant'] ?? ''),
                    'confirmedCount' => $confirmedCount,
                    'totalAmount' => $totalAmount,
                    'lastReservationDate' => $row['lastReservationDate'] ?? null,
                    'tier' => $this->resolveLoyaltyTier($confirmedCount, $vipThreshold),
                    'score' => $this->resolveLoyaltyScore($confirmedCount, $totalAmount),
                    'vipAccess' => $confirmedCount >= $vipThreshold,
                ];
            }
        }

        $selectedWaitlistEvent = null;
        $waitlistEntries = [];
        $remainingPlaces = 0;
        $pendingCount = 0;
        $inviteCount = 0;
        $latestPromotions = [];
        $waitlistEventId = $request->query->getInt('waitlist_event');

        if ($this->tableExists($em, 'waitlist_entry')) {
            if ($waitlistEventId > 0) {
                $selectedWaitlistEvent = $em->getRepository(Event::class)->find($waitlistEventId);
            }
            if (!$selectedWaitlistEvent && !empty($events)) {
                $selectedWaitlistEvent = $events[0];
            }
            if ($selectedWaitlistEvent instanceof Event) {
                $waitlistEntries = $waitlistEntryRepository->findByEventOrdered($selectedWaitlistEvent);
                $remainingPlaces = $eventCapacityService->remainingPlaces($selectedWaitlistEvent);
                $pendingCount = $waitlistEntryRepository->countByEventAndStatus($selectedWaitlistEvent, WaitlistEntry::STATUS_EN_ATTENTE);
                $inviteCount = $waitlistEntryRepository->countByEventAndStatus($selectedWaitlistEvent, WaitlistEntry::STATUS_INVITE);
            }
            $latestPromotions = $waitlistEntryRepository->findLatestPromotions(8);
        }

        $totalVip = count(array_filter($loyaltyClients, static fn (array $client): bool => (bool) ($client['vipAccess'] ?? false)));

        return $this->render('admin/gestion-events.html.twig', [
            'events' => $events,
            'eventStats' => $eventStats,
            'waitlistCountsByEvent' => $waitlistCountsByEvent,
            'participantsList' => $participantsList,
            'participantEventOptions' => $participantEventOptions,
            'participantStatusStats' => $participantStatusStats,
            'participantSearch' => $participantSearch,
            'participantEmailFilter' => $participantEmailFilter,
            'participantSort' => $participantSort,
            'participantStatusFilter' => $participantStatusFilter,
            'participantEventFilter' => $participantEventFilter,
            'loyaltyClients' => $loyaltyClients,
            'loyaltySearch' => $loyaltySearch,
            'loyaltySort' => $loyaltySort,
            'vipThreshold' => $vipThreshold,
            'totalVip' => $totalVip,
            'selectedWaitlistEvent' => $selectedWaitlistEvent,
            'waitlistEntries' => $waitlistEntries,
            'remainingPlaces' => $remainingPlaces,
            'pendingCount' => $pendingCount,
            'inviteCount' => $inviteCount,
            'latestPromotions' => $latestPromotions,
            'totalEvents' => count($events),
            'totalPendingWaitlist' => $totalPendingWaitlist,
            'totalInvitedWaitlist' => $totalInvitedWaitlist,
            'premiumFilter' => $premiumFilter,
            'activePanel' => $activePanel,
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

        $validation = $this->validateEventPayload($request);
        if ($validation['error']) {
            $this->addFlash('error', $validation['error']);
            return $this->redirectToRoute('admin_events');
        }
        $payload = $validation['data'];

        $event = new Event();
        $event->setTitre($payload['titre']);
        $event->setDescription($payload['description']);
        $event->setDateEvent($payload['dateEvent']);
        $event->setLieu($payload['lieu']);
        $event->setCapacite($payload['capacite']);
        $event->setTypeEvent($payload['typeEvent']);
        $event->setPrixEvent($payload['prixEvent']);
        $event->setIsPremium($payload['isPremium']);
        $event->setCreatedAt(new \DateTimeImmutable());

        $uploadedImage = $this->handleEventImageUpload($request->files->get('image_file'), $slugger);
        if ($uploadedImage['filename']) {
            $event->setImageEvent($uploadedImage['filename']);
        }

        $em->persist($event);
        $em->flush();
        $this->addFlash('success', 'Event created successfully.');

        return $this->redirectToRoute('admin_events');
    }

    #[Route('/events/{id}/update', name: 'events_update', methods: ['POST'])]
    public function eventsUpdate(Event $event, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
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
        $payload = $validation['data'];

        $event->setTitre($payload['titre']);
        $event->setDescription($payload['description']);
        $event->setDateEvent($payload['dateEvent']);
        $event->setLieu($payload['lieu']);
        $event->setCapacite($payload['capacite']);
        $event->setTypeEvent($payload['typeEvent']);
        $event->setPrixEvent($payload['prixEvent']);
        $event->setIsPremium($payload['isPremium']);

        $uploadedImage = $this->handleEventImageUpload($request->files->get('image_file'), $slugger);
        if ($uploadedImage['filename']) {
            $event->setImageEvent($uploadedImage['filename']);
        }

        $em->flush();
        $this->addFlash('success', 'Event updated successfully.');

        return $this->redirectToRoute('admin_events');
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

    #[Route('/waitlist', name: 'waitlist', methods: ['GET'])]
    public function waitlist(
        Request $request,
        EntityManagerInterface $em,
        WaitlistEntryRepository $waitlistEntryRepository,
        EventCapacityService $eventCapacityService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $events = $em->getRepository(Event::class)->findBy([], ['dateEvent' => 'ASC']);
        $selectedEventId = $request->query->getInt('eventId');
        $selectedEvent = null;
        if ($selectedEventId > 0) {
            $selectedEvent = $em->getRepository(Event::class)->find($selectedEventId);
        }
        if (!$selectedEvent && !empty($events)) {
            $selectedEvent = $events[0];
        }

        $entries = [];
        if ($selectedEvent) {
            $entries = $waitlistEntryRepository->findByEventOrdered($selectedEvent);
        }

        $remainingPlaces = $selectedEvent ? $eventCapacityService->remainingPlaces($selectedEvent) : 0;
        $pendingCount = $selectedEvent ? $waitlistEntryRepository->countByEventAndStatus($selectedEvent, WaitlistEntry::STATUS_EN_ATTENTE) : 0;
        $inviteCount = $selectedEvent ? $waitlistEntryRepository->countByEventAndStatus($selectedEvent, WaitlistEntry::STATUS_INVITE) : 0;
        $latestPromotions = $this->tableExists($em, 'waitlist_entry') ? $waitlistEntryRepository->findLatestPromotions(10) : [];

        return $this->render('admin/waitlist.html.twig', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'entries' => $entries,
            'remainingPlaces' => $remainingPlaces,
            'pendingCount' => $pendingCount,
            'inviteCount' => $inviteCount,
            'latestPromotions' => $latestPromotions,
        ]);
    }

    #[Route('/events/{id}/waitlist/invite-next', name: 'events_waitlist_invite_next', methods: ['POST'])]
    public function eventsWaitlistInviteNext(Event $event, Request $request, WaitlistService $waitlistService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_waitlist_invite_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToEventAdminPanel($request, $event);
        }

        try {
            $entry = $waitlistService->processNextInvite($event);
            if ($entry instanceof WaitlistEntry) {
                $this->addFlash('success', sprintf('Invitation envoyee a %s.', $entry->getEmail()));
            } else {
                $this->addFlash('info', 'Aucune invitation possible pour le moment.');
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible de traiter la prochaine invitation.');
        }

        return $this->redirectToEventAdminPanel($request, $event);
    }

    #[Route('/events/{id}/waitlist/expire', name: 'events_waitlist_expire', methods: ['POST'])]
    public function eventsWaitlistExpire(Event $event, Request $request, WaitlistService $waitlistService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_waitlist_expire_'.$event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToEventAdminPanel($request, $event);
        }

        try {
            $expiredCount = $waitlistService->expireInvitesForEvent($event);
            $this->addFlash('success', $expiredCount > 0
                ? sprintf('%d invitation(s) expiree(s) nettoyee(s).', $expiredCount)
                : 'Aucune invitation expiree a nettoyer.'
            );
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible de nettoyer les invitations expirees.');
        }

        return $this->redirectToEventAdminPanel($request, $event);
    }

    #[Route('/events/waitlist/{id}/cancel', name: 'events_waitlist_cancel', methods: ['POST'])]
    public function eventsWaitlistCancel(WaitlistEntry $entry, Request $request, WaitlistService $waitlistService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_events_waitlist_cancel_'.$entry->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToEventAdminPanel($request, $entry->getEvent());
        }

        try {
            $waitlistService->cancelEntry($entry);
            $this->addFlash('success', 'Entree waitlist annulee.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible d annuler cette entree waitlist.');
        }

        return $this->redirectToEventAdminPanel($request, $entry->getEvent());
    }

    #[Route('/trainings', name: 'trainings')]
    public function trainings(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $em->getRepository(User::class)->findBy([], ['id' => 'ASC']);
        $programs = [];
        $exerciseInEdit = null;
        if ($this->tableExists($em, 'fitness_exercise')) {
            $programs = $em->getRepository(FitnessExercise::class)->findBy([], ['updatedAt' => 'DESC']);
            $editId = $request->query->getInt('edit');
            if ($editId > 0) {
                $exerciseInEdit = $em->getRepository(FitnessExercise::class)->find($editId);
            }
        }

        return $this->render('admin/gestion-trainings.html.twig', [
            'users' => $users,
            'programs' => $programs,
            'exerciseInEdit' => $exerciseInEdit,
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
        if (!$this->tableHasColumns($em, 'fitness_program', [
            'id', 'title', 'description', 'category', 'level', 'duration_weeks',
            'sessions_per_week', 'session_duration', 'image_url', 'video_url',
            'is_public', 'created_at', 'updated_at', 'user_id',
        ])) {
            $this->addFlash('error', 'Training plan structure is not compatible with Symfony in fitopiabd.');
            return $this->redirectToRoute('admin_trainings');
        }

        $uploadedImage = $this->handleTrainingAssetUpload(
            $request->files->get('image_file'),
            'images',
            'training-plan-image',
            ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif']
        );
        if ($uploadedImage['error']) {
            $this->addFlash('error', $uploadedImage['error']);
            return $this->redirectToRoute('admin_trainings');
        }

        $uploadedVideo = $this->handleTrainingAssetUpload(
            $request->files->get('video_file'),
            'videos',
            'training-plan-video',
            ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v']
        );
        if ($uploadedVideo['error']) {
            $this->addFlash('error', $uploadedVideo['error']);
            return $this->redirectToRoute('admin_trainings');
        }

        $plan = new FitnessProgram();
        $plan->setTitle((string) $request->request->get('title'));
        $plan->setCategory((string) $request->request->get('category'));
        $plan->setLevel((string) $request->request->get('level'));
        $plan->setDescription((string) $request->request->get('description'));
        $plan->setDurationWeeks($request->request->getInt('duration_weeks', 4));
        $plan->setSessionsPerWeek($request->request->getInt('sessions_per_week', 3));
        $plan->setSessionDuration($request->request->getInt('session_duration', 45));
        $plan->setImageUrl($uploadedImage['path'] ?? $this->normalizeNullableString($request->request->get('image_url')));
        $plan->setVideoUrl($uploadedVideo['path'] ?? $this->normalizeNullableString($request->request->get('video_url')));
        $plan->setIsPublic((string) $request->request->get('is_public') === '1');

        $userId = $request->request->getInt('user_id');
        if ($userId > 0) {
            $user = $em->getRepository(User::class)->find($userId);
            if ($user instanceof User) {
                $plan->setUser($user);
            }
        }

        $programIds = array_map('intval', (array) $request->request->all('program_ids'));
        foreach (array_filter($programIds, static fn (int $id): bool => $id > 0) as $programId) {
            $exercise = $em->getRepository(FitnessExercise::class)->find($programId);
            if ($exercise instanceof FitnessExercise) {
                $plan->addExercise($exercise);
            }
        }

        $em->persist($plan);
        $em->flush();
        $this->addFlash('success', 'Plan added.');
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
        if (!$this->tableExists($em, 'fitness_exercise')) {
            $this->addFlash('error', 'Training exercise table is missing in fitopiabd.');
            return $this->redirectToRoute('admin_trainings');
        }

        $uploadedImage = $this->handleTrainingAssetUpload(
            $request->files->get('image_file'),
            'images',
            'training-program-image',
            ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif']
        );
        if ($uploadedImage['error']) {
            $this->addFlash('error', $uploadedImage['error']);
            return $this->redirectToRoute('admin_trainings');
        }

        $uploadedVideo = $this->handleTrainingAssetUpload(
            $request->files->get('video_file'),
            'videos',
            'training-program-video',
            ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v']
        );
        if ($uploadedVideo['error']) {
            $this->addFlash('error', $uploadedVideo['error']);
            return $this->redirectToRoute('admin_trainings');
        }

        $program = new FitnessExercise();
        $program->setName((string) $request->request->get('name'));
        $program->setMuscleGroup($this->normalizeFitnessMuscleGroup((string) $request->request->get('muscle_group')));
        $program->setDifficulty((string) $request->request->get('difficulty'));
        $program->setDescription((string) $request->request->get('description'));
        $program->setPlace((string) $request->request->get('place', 'both'));
        $program->setSets($request->request->getInt('sets'));
        $program->setRepetitions($request->request->getInt('repetitions'));
        $program->setDuration($request->request->getInt('duration'));
        $program->setImageUrl($uploadedImage['path'] ?? $this->normalizeNullableString($request->request->get('image_url')));
        $program->setVideoUrl($uploadedVideo['path'] ?? $this->normalizeNullableString($request->request->get('video_url')));

        try {
            $em->persist($program);
            $em->flush();
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to save this exercise in fitness_exercise.');
            return $this->redirectToRoute('admin_trainings');
        }

        $this->addFlash('success', 'Exercise added.');
        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/trainings/program/{id}/update', name: 'trainings_program_update', methods: ['POST'])]
    public function trainingsProgramUpdate(FitnessExercise $program, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('admin_trainings_program_update_'.$program->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_trainings', ['edit' => $program->getId()]);
        }
        if (!$this->tableExists($em, 'fitness_exercise')) {
            $this->addFlash('error', 'Training exercise table is missing in fitopiabd.');
            return $this->redirectToRoute('admin_trainings');
        }

        $uploadedImage = $this->handleTrainingAssetUpload(
            $request->files->get('image_file'),
            'images',
            'training-program-image',
            ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif']
        );
        if ($uploadedImage['error']) {
            $this->addFlash('error', $uploadedImage['error']);
            return $this->redirectToRoute('admin_trainings', ['edit' => $program->getId()]);
        }

        $uploadedVideo = $this->handleTrainingAssetUpload(
            $request->files->get('video_file'),
            'videos',
            'training-program-video',
            ['mp4', 'webm', 'ogg', 'mov', 'avi', 'm4v']
        );
        if ($uploadedVideo['error']) {
            $this->addFlash('error', $uploadedVideo['error']);
            return $this->redirectToRoute('admin_trainings', ['edit' => $program->getId()]);
        }

        $program->setName((string) $request->request->get('name'));
        $program->setMuscleGroup($this->normalizeFitnessMuscleGroup((string) $request->request->get('muscle_group')));
        $program->setDifficulty((string) $request->request->get('difficulty'));
        $program->setDescription((string) $request->request->get('description') ?: null);
        $program->setPlace((string) $request->request->get('place', 'both'));
        $program->setSets($request->request->getInt('sets'));
        $program->setRepetitions($request->request->getInt('repetitions'));
        $program->setDuration($request->request->getInt('duration'));
        if ($uploadedImage['path']) {
            $program->setImageUrl($uploadedImage['path']);
        }
        if ($uploadedVideo['path']) {
            $program->setVideoUrl($uploadedVideo['path']);
        }

        try {
            $em->flush();
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to update this exercise.');
            return $this->redirectToRoute('admin_trainings', ['edit' => $program->getId()]);
        }

        $this->addFlash('success', 'Exercise updated.');
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
        if (!$this->tableExists($em, 'fitness_exercise')) {
            $this->addFlash('error', 'Training exercise table is missing in fitopiabd.');
            return $this->redirectToRoute('admin_trainings');
        }

        try {
            $em->remove($program);
            $em->flush();
        } catch (\Throwable) {
            $this->addFlash('error', 'Unable to delete this exercise.');
            return $this->redirectToRoute('admin_trainings');
        }

        $this->addFlash('success', 'Exercise deleted.');
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
        if (!$this->tableHasColumns($em, 'fitness_trend', [
            'id', 'title', 'category', 'description', 'image_url', 'is_active',
            'created_at', 'updated_at',
        ])) {
            $this->addFlash('error', 'Training trend structure is not compatible with Symfony in fitopiabd.');
            return $this->redirectToRoute('admin_trainings');
        }

        $trend = new FitnessTrend();
        $trend->setTitle((string) $request->request->get('title'));
        $trend->setCategory((string) $request->request->get('category'));
        $trend->setDescription((string) $request->request->get('description'));
        $trend->setIsActive((string) $request->request->get('is_active') === '1');

        $em->persist($trend);
        $em->flush();
        $this->addFlash('success', 'Trend added.');
        return $this->redirectToRoute('admin_trainings');
    }

    #[Route('/nutrition', name: 'nutrition')]
    public function nutrition(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $currentTab = (string) $request->query->get('tab', 'create');
        if (!in_array($currentTab, ['create', 'update', 'delete', 'explore'], true)) {
            $currentTab = 'create';
        }
        $maxItems = 40;

        $repas = new Repas();
        $repasForm = $this->createForm(RepasFormType::class, $repas, [
            'action' => $this->generateUrl('admin_nutrition'),
            'attr' => ['id' => 'admin-nutrition-create-form'],
        ]);
        $repasForm->handleRequest($request);

        if ($repasForm->isSubmitted() && $repasForm->isValid()) {
            $this->applyMealCaloriesFromMacros($repas);
            $em->persist($repas);
            $em->flush();

            $this->addFlash('success', 'Repas cree avec succes.');

            return $this->redirectToRoute('admin_nutrition', [
                'tab' => 'explore',
            ]);
        }

        $repasRepository = $em->getRepository(Repas::class);
        $repasList = $repasRepository->findBy([], ['dateRepas' => 'DESC'], $maxItems);
        $totalRepas = $repasRepository->count([]);
        $items = [];
        $editForms = [];

        foreach ($repasList as $item) {
            $items[] = [
                'id' => $item->getId(),
                'nomRepas' => $item->getNomRepas(),
                'typeRepas' => $item->getTypeRepas(),
                'calories' => $item->getCalories(),
                'email' => $item->getUser()?->getEmail() ?? '',
                'date' => $item->getDateRepas()->format('Y-m-d H:i'),
                'dateRaw' => $item->getDateRepas()->format(DATE_ATOM),
            ];
        }

        $selectedEditId = $request->query->getInt('edit');
        if ($currentTab === 'update' && $selectedEditId <= 0 && $repasList !== []) {
            $selectedEditId = (int) $repasList[0]->getId();
        }

        foreach ($repasList as $item) {
            if ((int) $item->getId() !== $selectedEditId) {
                continue;
            }

            $editForms[$item->getId()] = $this->createForm(RepasFormType::class, $item, [
                'action' => $this->generateUrl('admin_nutrition_repas_update', ['id' => $item->getId()]),
                'attr' => ['class' => 'admin-nutrition-edit-form'],
            ])->createView();
            break;
        }

        return $this->render('admin/gestion-nutrition.html.twig', [
            'repasList' => $repasList,
            'items' => $items,
            'editForms' => $editForms,
            'repasForm' => $repasForm->createView(),
            'currentTab' => $currentTab,
            'selectedEditId' => $selectedEditId,
            'totalRepas' => $totalRepas,
            'maxItems' => $maxItems,
        ]);
    }

    #[Route('/nutrition/repas/{id}/update', name: 'nutrition_repas_update', methods: ['POST'])]
    public function nutritionRepasUpdate(Repas $repas, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(RepasFormType::class, $repas, [
            'action' => $this->generateUrl('admin_nutrition_repas_update', ['id' => $repas->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyMealCaloriesFromMacros($repas);
            $em->flush();
            $this->addFlash('success', 'Repas modifie avec succes.');
        } else {
            $this->addFlash('error', 'Impossible de modifier ce repas. Verifiez les champs saisis.');
        }

        return $this->redirectToRoute('admin_nutrition', [
            'tab' => 'update',
            'edit' => $repas->getId(),
        ]);
    }

    #[Route('/nutrition/repas/{id}/delete', name: 'nutrition_repas_delete', methods: ['POST'])]
    public function nutritionRepasDelete(Repas $repas, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('delete_repas_'.$repas->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de securite invalide.');

            return $this->redirectToRoute('admin_nutrition', [
                'tab' => 'delete',
            ]);
        }

        $em->remove($repas);
        $em->flush();
        $this->addFlash('success', 'Repas supprime avec succes.');

        return $this->redirectToRoute('admin_nutrition', [
            'tab' => 'delete',
        ]);
    }

    #[Route('/nutrition/repas/{id}/pdf', name: 'nutrition_repas_pdf', methods: ['GET'])]
    public function nutritionRepasPdf(Repas $repas): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $html = sprintf(
            '<html><body style="font-family: DejaVu Sans, sans-serif; color: #0f172a;">
                <h1 style="color:#0f7a5f;">Fiche repas</h1>
                <p><strong>Nom:</strong> %s</p>
                <p><strong>Utilisateur:</strong> %s</p>
                <p><strong>Date:</strong> %s</p>
                <p><strong>Type:</strong> %s</p>
                <p><strong>Calories:</strong> %s kcal</p>
                <p><strong>Proteines:</strong> %s g</p>
                <p><strong>Glucides:</strong> %s g</p>
                <p><strong>Lipides:</strong> %s g</p>
                <p><strong>Commentaire:</strong> %s</p>
            </body></html>',
            htmlspecialchars($repas->getNomRepas(), ENT_QUOTES),
            htmlspecialchars($repas->getUser()?->getEmail() ?? '-', ENT_QUOTES),
            htmlspecialchars($repas->getDateRepas()->format('Y-m-d H:i'), ENT_QUOTES),
            htmlspecialchars($repas->getTypeRepas(), ENT_QUOTES),
            (string) ($repas->getCalories() ?? 0),
            (string) ($repas->getProteines() ?? 0),
            (string) ($repas->getGlucides() ?? 0),
            (string) ($repas->getLipides() ?? 0),
            htmlspecialchars($repas->getCommentaire() ?? '-', ENT_QUOTES)
        );

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="repas-'.$repas->getId().'.pdf"',
        ]);
    }

    #[Route('/nutrition/user/{id}/regimes', name: 'nutrition_user_regimes', methods: ['GET'])]
    public function nutritionUserRegimes(User $user, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $regimes = $em->getRepository(\App\Entity\RegimeAlimentaire::class)->findBy(
            ['user' => $user],
            ['id' => 'DESC']
        );

        $payload = array_map(static function (\App\Entity\RegimeAlimentaire $regime): array {
            return [
                'id' => $regime->getId(),
                'label' => 'Regime #'.$regime->getId().' - '.($regime->getTypeSante() ?? 'N/A').' - '.($regime->getCaloriesCibles() ?? 0).' kcal',
            ];
        }, $regimes);

        return $this->json([
            'items' => $payload,
        ]);
    }

    #[Route('/loyalty', name: 'loyalty')]
    public function loyalty(Request $request, EntityManagerInterface $em, LoyaltyService $loyaltyService): Response
    {
        $filter = strtolower(trim((string) $request->query->get('filter', 'all')));
        if (!in_array($filter, ['all', 'vip', 'standard'], true)) {
            $filter = 'all';
        }

        $vipThreshold = $loyaltyService->getVipReservationThreshold();
        $rows = $em->getConnection()->fetchAllAssociative(
            'SELECT LOWER(email_participant) AS email,
                    SUM(CASE WHEN statut IN (:confirmed, :used) THEN 1 ELSE 0 END) AS confirmedCount
             FROM reservation
             WHERE email_participant IS NOT NULL AND TRIM(email_participant) <> \'\'
             GROUP BY LOWER(email_participant)
             ORDER BY confirmedCount DESC, email ASC',
            [
                'confirmed' => Reservation::STATUS_CONFIRMED,
                'used' => Reservation::STATUS_USED,
            ]
        );

        $clients = [];
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                continue;
            }

            $confirmedCount = (int) ($row['confirmedCount'] ?? 0);
            $status = $confirmedCount >= $vipThreshold ? 'VIP' : 'Standard';
            if ($filter === 'vip' && $status !== 'VIP') {
                continue;
            }
            if ($filter === 'standard' && $status !== 'Standard') {
                continue;
            }

            $clients[] = [
                'email' => $email,
                'confirmedCount' => $confirmedCount,
                'loyaltyStatus' => $status,
            ];
        }

        $totalClients = count($rows);
        $totalVip = count(array_filter($rows, static fn (array $row): bool => (int) ($row['confirmedCount'] ?? 0) >= $vipThreshold));

        return $this->render('admin/loyalty/index.html.twig', [
            'clients' => $clients,
            'filter' => $filter,
            'totalClients' => $totalClients,
            'totalVip' => $totalVip,
            'vipThreshold' => $vipThreshold,
        ]);
    }

    #[Route('/profile', name: 'profile')]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        LegacyUserBridgeService $legacyUserBridge
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $legacyUserBridge->syncUser($user);
            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile.html.twig', [
            'profileForm' => $form->createView(),
        ]);
    }

    /**
     * @return array<string, array{status: string, phone: string, transactionId: ?string}>
     */
    private function buildParticipantReservationMetaMap(EntityManagerInterface $em): array
    {
        if (!$this->tableExists($em, 'reservation')) {
            return [];
        }

        $rows = $em->getConnection()->fetchAllAssociative(
            <<<SQL
SELECT
    r.id_event AS eventId,
    LOWER(r.email_participant) AS emailKey,
    r.statut AS status,
    COALESCE(NULLIF(TRIM(r.telephone_participant), ''), '-') AS phone,
    r.transaction_id AS transactionId
FROM reservation r
INNER JOIN (
    SELECT
        id_event,
        LOWER(email_participant) AS email_key,
        MAX(date_reservation) AS latest_date
    FROM reservation
    WHERE email_participant IS NOT NULL AND TRIM(email_participant) <> ''
    GROUP BY id_event, LOWER(email_participant)
) latest
    ON latest.id_event = r.id_event
   AND latest.email_key = LOWER(r.email_participant)
   AND latest.latest_date = r.date_reservation
WHERE r.email_participant IS NOT NULL AND TRIM(r.email_participant) <> ''
SQL
        );

        $map = [];
        foreach ($rows as $row) {
            $eventId = (int) ($row['eventId'] ?? 0);
            $emailKey = trim((string) ($row['emailKey'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
        if ($eventId <= 0 || $emailKey === '' || $status === '') {
            continue;
        }
            $map[$eventId.'|'.$emailKey] = [
                'status' => $status,
                'phone' => trim((string) ($row['phone'] ?? '-')) ?: '-',
                'transactionId' => isset($row['transactionId']) ? (string) $row['transactionId'] : null,
            ];
        }

        return $map;
    }

    private function normalizeReservationStatusSlug(string $status): string
    {
        return match (mb_strtolower(trim($status))) {
            'utilisee' => 'utilisee',
            'annulee' => 'annulee',
            'en_attente_paiement' => 'en_attente_paiement',
            'payee', 'payé', 'paye', 'paid' => 'payee',
            default => 'confirmee',
        };
    }

    private function resolveLoyaltyTier(int $confirmedCount, int $vipThreshold): string
    {
        if ($confirmedCount >= $vipThreshold) {
            return 'VIP';
        }

        if ($confirmedCount >= max(3, $vipThreshold - 2)) {
            return 'Silver';
        }

        return 'Bronze';
    }

    private function resolveLoyaltyScore(int $confirmedCount, float $totalAmount): int
    {
        return ($confirmedCount * 10) + (int) round($totalAmount / 10);
    }

    private function redirectToEventAdminPanel(Request $request, ?Event $event = null): Response
    {
        $eventId = $event?->getId();
        $redirect = strtolower(trim((string) $request->request->get('_redirect', 'events')));

        if ($redirect === 'waitlist') {
            $params = [];
            if ($eventId !== null) {
                $params['eventId'] = $eventId;
            }

            return $this->redirectToRoute('admin_waitlist', $params);
        }

        $params = ['panel' => 'waitlist'];
        if ($eventId !== null) {
            $params['waitlist_event'] = $eventId;
        }

        return $this->redirectToRoute('admin_events', $params);
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
        $newFilename = 'event-'.uniqid().'.'.$this->safeUploadedFileExtension($imageFile, 'jpg');
        try {
            $imageFile->move($this->getParameter('kernel.project_dir').'/public/uploads/events', $newFilename);
        } catch (FileException) {
            return ['filename' => null, 'error' => 'Upload failed'];
        }
        return ['filename' => $newFilename, 'error' => null];
    }

    private function handleTrainingAssetUpload(?UploadedFile $file, string $subdirectory, string $prefix, array $allowedExtensions): array
    {
        if (!$file instanceof UploadedFile) {
            return ['path' => null, 'error' => null];
        }

        $extension = strtolower($this->safeUploadedFileExtension($file, 'bin'));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['path' => null, 'error' => 'Unsupported file format for training upload.'];
        }

        $uploadRoot = $this->getParameter('kernel.project_dir').'/public/uploads/trainings/'.$subdirectory;
        if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0777, true) && !is_dir($uploadRoot)) {
            return ['path' => null, 'error' => 'Could not create the training upload folder.'];
        }

        $newFilename = $prefix.'-'.uniqid().'.'.$extension;

        try {
            $file->move($uploadRoot, $newFilename);
        } catch (FileException) {
            return ['path' => null, 'error' => 'The file upload failed.'];
        }

        return ['path' => '/uploads/trainings/'.$subdirectory.'/'.$newFilename, 'error' => null];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeFitnessMuscleGroup(?string $group): string
    {
        $value = trim((string) $group);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = strtolower($ascii !== false ? $ascii : $value);
        $normalized = str_replace(['_', '-'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return match ($normalized) {
            'poitrine' => 'Poitrine',
            'dos' => 'Dos',
            'jambes' => 'Jambes',
            'fessier', 'fessiers' => 'Fessier',
            'epaules' => 'Epaules',
            'biceps' => 'Biceps',
            'triceps' => 'Triceps',
            'avant bras' => 'Avant-bras',
            'abdos', 'abdominaux' => 'Abdos',
            'entrainement du corps entier', 'corps entier', 'full body' => 'Entrainement du corps entier',
            default => $value !== '' ? $value : 'Entrainement du corps entier',
        };
    }

    private function safeUploadedFileExtension(UploadedFile $file, string $fallback = 'bin'): string
    {
        $clientExtension = strtolower(trim((string) $file->getClientOriginalExtension()));
        if ($clientExtension !== '' && preg_match('/^[a-z0-9]+$/', $clientExtension)) {
            return $clientExtension;
        }

        $nameExtension = strtolower(trim((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)));
        if ($nameExtension !== '' && preg_match('/^[a-z0-9]+$/', $nameExtension)) {
            return $nameExtension;
        }

        try {
            $guessedExtension = $file->guessExtension();
            if (is_string($guessedExtension) && $guessedExtension !== '' && preg_match('/^[a-z0-9]+$/', $guessedExtension)) {
                return strtolower($guessedExtension);
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    private function buildEventStats(array $events, WaitlistEntryRepository $waitlistEntryRepository, EventCapacityService $eventCapacityService): array
    {
        $stats = [];
        foreach ($events as $event) {
            $activeReservations = $eventCapacityService->countActiveReservations($event);
            $remainingPlaces = max(0, (int) $event->getCapacite() - $activeReservations);
            $stats[(int) $event->getId()] = [
                'activeReservations' => $activeReservations,
                'remainingPlaces' => $remainingPlaces,
                'isFull' => $remainingPlaces <= 0,
            ];
        }
        return $stats;
    }

    private function fetchForumInteractions(EntityManagerInterface $em): array
    {
        $connection = $em->getConnection();
        return $connection->fetchAllAssociative(
            '
            SELECT
                c.id AS id,
                "comment" AS type,
                c.content AS content,
                c.user_id AS userId,
                c.forum_id AS postId
            FROM forum_comments c
            UNION ALL
            SELECT
                l.id AS id,
                "like" AS type,
                NULL AS content,
                l.user_id AS userId,
                l.forum_id AS postId
            FROM forum_likes l
            UNION ALL
            SELECT
                r.id AS id,
                "repost" AS type,
                NULL AS content,
                r.user_id AS userId,
                r.forum_id AS postId
            FROM forum_reposts r
            '
        );
    }

    private function forumInteractionTableForType(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'comment' => 'forum_comments',
            'like' => 'forum_likes',
            'repost' => 'forum_reposts',
            default => null,
        };
    }



    private function normalizeForumImage(?string $imagePath): ?string
    {
        return $imagePath ? basename($imagePath) : null;
    }

    private function applyMealCaloriesFromMacros(Repas $repas): void
    {
        $protein = max(0, (int) ($repas->getProteines() ?? 0));
        $carbs = max(0, (int) ($repas->getGlucides() ?? 0));
        $fat = max(0, (int) ($repas->getLipides() ?? 0));

        if ($protein === 0 && $carbs === 0 && $fat === 0) {
            return;
        }

        $repas->setCalories(($protein * 4) + ($carbs * 4) + ($fat * 9));
    }

    private function tableExists(EntityManagerInterface $em, string $tableName): bool
    {
        $connection = $em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        return $schemaManager->tablesExist([$tableName]);
    }

    /**
     * @param string[] $requiredColumns
     */
    private function tableHasColumns(EntityManagerInterface $em, string $tableName, array $requiredColumns): bool
    {
        $connection = $em->getConnection();
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
            return false;
        }

        $existingColumns = array_map(
            static fn ($column): string => strtolower($column->getName()),
            $schemaManager->listTableColumns($tableName)
        );

        foreach ($requiredColumns as $requiredColumn) {
            if (!in_array(strtolower($requiredColumn), $existingColumns, true)) {
                return false;
            }
        }

        return true;
    }
}

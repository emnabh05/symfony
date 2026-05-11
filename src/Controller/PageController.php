<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\BlogPost;
use App\Entity\DmConversation;
use App\Entity\DmMessage;
use App\Entity\FitnessExercise;
use App\Entity\FitnessPlan;
use App\Entity\FitnessProgram;
use App\Entity\FitnessTrend;
use App\Entity\User;
use App\Entity\RegimeAlimentaire;
use App\Entity\Repas;
use App\Form\BlogPostType;
use App\Form\RegimeAlimentaireFormType;
use App\Service\JavaDietPlannerService;
use App\Service\LegacyUserBridgeService;
use App\Service\NutritionRegimeCalculator;
use App\Service\ExploreSummaryService;
use App\Service\DailyGoalMailerService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;

use App\Entity\Event;
use App\Entity\Participation;
use App\Repository\EventReviewRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ReservationRepository;
use App\Repository\WaitlistEntryRepository;
use App\Service\EventParticipantEmailResolver;
use App\Service\EventCapacityService;
use App\Service\EventRecommendationService;
use App\Service\LoyaltyService;
class PageController extends AbstractController
{
    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    public function __construct(
    ) {
    }

    #[Route('/', name: 'home')]
    #[Route('/public', name: 'home_public')]
    #[Route('/public/', name: 'home_public_slash')]
    #[Route('/index.php', name: 'home_index_php')]
    #[Route('/index.php/', name: 'home_index_php_slash')]
    public function home(): Response
    {
        return $this->render('pages/index.html.twig');
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    #[Route('/services', name: 'services')]
    public function services(): Response
    {
        return $this->render('pages/services.html.twig');
    }

    #[Route('/pricing', name: 'pricing')]
    public function pricing(): Response
    {
        return $this->render('pages/pricing.html.twig');
    }

    #[Route('/blog', name: 'blog')]
    public function blog(): Response
    {
        return $this->render('pages/blog.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('pages/contact.html.twig');
    }

    #[Route('/supplements', name: 'supplements')]
    public function supplements(): Response
    {
        return $this->redirectToRoute('app_shop_index');
    }

    #[Route('/fitness-planner', name: 'fitness_planner', methods: ['GET', 'POST'])]
    public function fitnessPlanner(Request $request, EntityManagerInterface $em): Response
    {
        $fitnessTables = $this->existingTables($em, [
            'fitness_program',
            'fitness_exercise',
            'fitness_program_exercise',
            'fitness_program_image_link',
            'fitness_trend',
            'fitness_plan',
        ]);
        $programTablesReady = $fitnessTables['fitness_program']
            && $fitnessTables['fitness_exercise']
            && $fitnessTables['fitness_program_exercise'];
        $exerciseTablesReady = $fitnessTables['fitness_exercise'];
        $tablesReady = $programTablesReady || $exerciseTablesReady;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('fitness_planner', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token.');
                return $this->redirectToRoute('fitness_planner');
            }

            $action = (string) $request->request->get('action');
            $requiresExerciseTable = in_array($action, ['create_exercise', 'update_exercise', 'delete_exercise'], true);
            $requiresProgramTables = in_array($action, ['create_program', 'update_program', 'delete_program'], true);

            if (($requiresExerciseTable && !$exerciseTablesReady) || ($requiresProgramTables && !$programTablesReady)) {
                return $this->redirectToRoute('fitness_planner');
            }

            $this->handleFitnessPlannerPost($request, $em);
            return $this->redirectToRoute('fitness_planner', [
                'view' => (string) $request->request->get('redirect_view', 'manage'),
            ]);
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'newest');
        $view = (string) $request->query->get('view', 'manage');
        $programEditId = $request->query->getInt('program_edit', 0);
        $exerciseEditId = $request->query->getInt('exercise_edit', 0);

        $programs = [];
        $exercises = [];
        $trends = [];
        $programInEdit = null;
        $exerciseInEdit = null;
        $stats = ['programs' => 0, 'exercises' => 0, 'avgDuration' => 0];
        $programImageLinks = [];
        $savedPlans = [];
        $plansPersistAvailable = false;
        $exerciseGroups = [];
        $exerciseLibraryByGroup = [];

        if ($programTablesReady) {
            $programsQb = $em->getRepository(FitnessProgram::class)->createQueryBuilder('p');
            if ($search !== '') {
                $programsQb
                    ->andWhere('LOWER(p.title) LIKE :q OR LOWER(p.category) LIKE :q OR LOWER(p.level) LIKE :q')
                    ->setParameter('q', '%'.mb_strtolower($search).'%');
            }

            match ($sort) {
                'oldest' => $programsQb->orderBy('p.createdAt', 'ASC'),
                'title_asc' => $programsQb->orderBy('p.title', 'ASC'),
                'title_desc' => $programsQb->orderBy('p.title', 'DESC'),
                'duration_asc' => $programsQb->orderBy('p.sessionDuration', 'ASC'),
                'duration_desc' => $programsQb->orderBy('p.sessionDuration', 'DESC'),
                default => $programsQb->orderBy('p.createdAt', 'DESC'),
            };

            $programs = $programsQb
                ->setMaxResults(96)
                ->getQuery()
                ->getResult();
            $stats['programs'] = count($programs);

            $durations = array_map(
                static fn (FitnessProgram $program): int => $program->getSessionDuration(),
                $programs
            );
            $stats['avgDuration'] = count($durations) > 0
                ? (int) round(array_sum($durations) / count($durations))
                : 0;

            if ($programEditId > 0) {
                $programInEdit = $em->getRepository(FitnessProgram::class)->find($programEditId);
            }

            $connection = $em->getConnection();
            if ($fitnessTables['fitness_program_image_link']) {
                $rows = $connection->fetchAllAssociative('SELECT program_name, image_url FROM fitness_program_image_link');
                foreach ($rows as $row) {
                    $name = (string) ($row['program_name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $programImageLinks[$name] = (string) ($row['image_url'] ?? '');
                }
            }

            if ($fitnessTables['fitness_trend']) {
                $trends = $em->getRepository(FitnessTrend::class)->findBy(
                    ['isActive' => true],
                    ['updatedAt' => 'DESC'],
                    30
                );
            }

            $plansPersistAvailable = $fitnessTables['fitness_plan'] && $this->getUser() instanceof User;
            if ($plansPersistAvailable) {
                $plans = $em->getRepository(FitnessPlan::class)->findBy(
                    ['user' => $this->getUser()],
                    ['createdAt' => 'DESC'],
                    50
                );
                $savedPlans = array_map(fn (FitnessPlan $plan) => $this->serializeFitnessPlan($plan), $plans);
            }
        }

        if ($exerciseTablesReady) {
            $exercises = $em->getConnection()->fetchAllAssociative(
                'SELECT
                    id,
                    name,
                    description,
                    muscle_group AS muscleGroup,
                    difficulty,
                    sets_count AS sets,
                    repetitions,
                    duration,
                    place,
                    video_url AS videoUrl,
                    image_url AS imageUrl,
                    created_at AS createdAt,
                    updated_at AS updatedAt
                 FROM fitness_exercise
                 ORDER BY created_at DESC
                 LIMIT 96'
            );
            $exerciseGroups = $this->buildExerciseGroups($exercises);
            $exerciseLibraryByGroup = $this->buildExerciseLibraryByGroup($exercises);

            if ($exerciseEditId > 0) {
                $exerciseInEdit = $em->getRepository(FitnessExercise::class)->find($exerciseEditId);
            }

            $stats['exercises'] = count($exercises);
        }

        return $this->render('pages/fitness-planner.html.twig', [
            'tablesReady' => $tablesReady,
            'programs' => $programs,
            'exercises' => $exercises,
            'programInEdit' => $programInEdit,
            'exerciseInEdit' => $exerciseInEdit,
            'trends' => $trends,
            'search' => $search,
            'sort' => $sort,
            'activeView' => in_array($view, ['plans', 'manage', 'exercises', 'explore', 'coach'], true) ? $view : 'manage',
            'stats' => $stats,
            'programImageLinks' => $programImageLinks,
            'savedPlans' => $savedPlans,
            'plansPersistAvailable' => $plansPersistAvailable,
            'exerciseGroups' => $exerciseGroups,
            'exerciseLibraryByGroup' => $exerciseLibraryByGroup,
        ]);
    }

    #[Route('/fitopia-games', name: 'fitopia_games')]
    public function fitopiaGames(): Response
    {
        return $this->render('pages/games.html.twig', [
            'skip_user_context' => true,
        ]);
    }

    #[Route('/fitopia-reels', name: 'fitopia_reels')]
    public function fitopiaReels(): Response
    {
        return $this->render('pages/reels.html.twig', [
            'skip_user_context' => true,
            'reels' => [
                [
                    'title' => 'Chest Builder Machine',
                    'author' => 'Fitopia Coach',
                    'caption' => 'Controlled chest press to activate the pecs and front delts.',
                    'video' => 'videos/fitness/developpe-incline-machine.mp4',
                    'likes' => 124,
                    'comments' => 19,
                ],
                [
                    'title' => 'Full Abs Circuit',
                    'author' => 'Healthy Core Lab',
                    'caption' => 'Short abs session to wake up the core and keep the rhythm high.',
                    'video' => 'videos/fitness/local/abdos-complet-20min.mp4',
                    'likes' => 212,
                    'comments' => 34,
                ],
                [
                    'title' => 'Arm Fly Focus',
                    'author' => 'Upper Body Team',
                    'caption' => 'Chest fly variation with a clean tempo and stronger squeeze.',
                    'video' => 'videos/fitness/arm-chest-flyes-poulie.mp4',
                    'likes' => 96,
                    'comments' => 12,
                ],
                [
                    'title' => 'Back Home Session',
                    'author' => 'Fitopia Reels',
                    'caption' => 'Quick at-home back routine for posture, pull strength and control.',
                    'video' => 'videos/fitness/local/back-home-5min.mp4',
                    'likes' => 175,
                    'comments' => 21,
                ],
            ],
        ]);
    }

    #[Route('/fitness-planner/plans', name: 'fitness_planner_plan_create', methods: ['POST'])]
    public function createFitnessPlannerPlan(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('fitness_planner', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_csrf'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        if (!$this->fitnessPlansTableExist($em)) {
            return new JsonResponse(['ok' => false, 'error' => 'missing_table'], 400);
        }

        $title = trim((string) $request->request->get('title', ''));
        $place = trim((string) $request->request->get('place', '')) ?: null;
        $programLabel = trim((string) $request->request->get('program_label', ''));
        $estimatedMinutes = max(0, $request->request->getInt('estimated_minutes', 0)) ?: null;

        $rawExercises = json_decode((string) $request->request->get('exercises', '[]'), true);
        $normalizedExercises = [];
        if (is_array($rawExercises)) {
            foreach ($rawExercises as $exercise) {
                if (!is_array($exercise)) {
                    continue;
                }
                $name = trim((string) ($exercise['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $normalizedExercises[] = [
                    'name' => $name,
                    'video' => trim((string) ($exercise['video'] ?? '')),
                ];
            }
        }

        if (empty($normalizedExercises)) {
            return new JsonResponse(['ok' => false, 'error' => 'missing_exercises'], 400);
        }

        $plan = new FitnessPlan();
        $plan->setTitle($title !== '' ? $title : sprintf('Ma seance (%d exercices)', count($normalizedExercises)));
        $plan->setPlace($place);
        $plan->setEstimatedMinutes($estimatedMinutes);
        $plan->setUser($user);
        $plan->setExercisesData($normalizedExercises);

        $program = $this->resolveProgramForPlan($programLabel, $em);
        if ($program) {
            $plan->setProgram($program);
        }

        $em->persist($plan);
        $em->flush();

        $payload = $this->serializeFitnessPlan($plan);
        if ($programLabel !== '' && $payload['program'] === '') {
            $payload['program'] = $programLabel;
        }

        return new JsonResponse(['ok' => true, 'plan' => $payload]);
    }

    #[Route('/fitness-planner/plans/{id}/delete', name: 'fitness_planner_plan_delete', methods: ['POST'])]
    public function deleteFitnessPlannerPlan(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('fitness_planner', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_csrf'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        /** @var FitnessPlan|null $plan */
        $plan = $em->getRepository(FitnessPlan::class)->find($id);
        if (!$plan) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], 404);
        }

        if ($plan->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $em->remove($plan);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/fitness-planner/coaches/email', name: 'fitness_planner_coach_send_email', methods: ['POST'])]
    public function sendFitnessCoachEmail(
        Request $request,
        MailerInterface $mailer
    ): JsonResponse
    {
        if (!$this->isCsrfTokenValid('fitness_planner', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'invalid_csrf'], 400);
        }

        $kind = trim((string) $request->request->get('kind', 'message'));
        $coachName = trim((string) $request->request->get('coach_name', 'Coach'));
        $coachSpeciality = trim((string) $request->request->get('coach_speciality', ''));
        $coachPhone = trim((string) $request->request->get('coach_phone', ''));
        $coachEmail = trim((string) $request->request->get('coach_email', ''));
        $reservationDate = trim((string) $request->request->get('reservation_date', ''));
        $reservationSlot = trim((string) $request->request->get('reservation_slot', ''));
        $clientMessage = trim((string) $request->request->get('client_message', ''));

        if ($kind === 'reservation' && ($reservationDate === '' || $reservationSlot === '')) {
            return new JsonResponse(['ok' => false, 'error' => 'missing_reservation_fields'], 400);
        }

        $sender = 'Client web';
        $user = $this->getUser();
        if ($user instanceof User) {
            $name = trim($user->getFirstName().' '.$user->getLastName());
            $sender = $name !== '' ? $name : ($user->getEmail() ?: 'Client web');
        }

        if ($kind === 'reservation') {
            $emailBody = implode("\n", [
                'Fitopia - Reservation client',
                'Coach: '.$coachName.($coachSpeciality !== '' ? ' ('.$coachSpeciality.')' : ''),
                'Client: '.$sender,
                'Date: '.$reservationDate,
                'Creneau: '.$reservationSlot,
                'Demande: '.($clientMessage !== '' ? $clientMessage : 'Je confirme ma reservation.'),
            ]);
        } else {
            $emailBody = implode("\n", [
                'Fitopia - Message client',
                'Coach: '.$coachName.($coachSpeciality !== '' ? ' ('.$coachSpeciality.')' : ''),
                'Client: '.$sender,
                'Message: '.($clientMessage !== '' ? $clientMessage : 'Bonjour, je souhaite vous contacter.'),
            ]);
        }

        $fallbackCoachEmail = (string) ($_ENV['COACH_FALLBACK_EMAIL'] ?? 'ahmedchebbi323@gmail.com');
        $emailStatus = [
            'status' => 'skipped',
            'message' => 'Coach email not provided.',
        ];

        // Force all reservations/messages to be delivered to the single mailbox.
        $coachEmail = $fallbackCoachEmail;
        $emailStatus = [
            'status' => 'forced',
            'message' => 'Forced recipient configured. All coach emails go to fallback.',
        ];

        if ($coachEmail !== '') {
            $from = trim((string) ($_ENV['MAILER_FROM'] ?? $_SERVER['MAILER_FROM'] ?? 'no-reply@fitopia.local'));
            $subject = $kind === 'reservation'
                ? 'Fitopia - Nouvelle reservation coach'
                : 'Fitopia - Nouveau message client';

            try {
                $email = (new Email())
                    ->from(new Address($from, 'Fitopia'))
                    ->to(new Address($coachEmail))
                    ->subject($subject)
                    ->text($emailBody);
                $mailer->send($email);
                $emailStatus = ['status' => 'sent'];
            } catch (\Throwable $exception) {
                $emailStatus = [
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return new JsonResponse([
            'ok' => true,
            'email' => $emailStatus,
        ]);
    }

    #[Route('/events', name: 'events')]
    public function events(
        Request $request,
        EntityManagerInterface $em,
        FavoriteRepository $favoriteRepository,
        EventReviewRepository $eventReviewRepository,
        EventParticipantEmailResolver $emailResolver,
        EventCapacityService $eventCapacityService,
        ReservationRepository $reservationRepository,
        WaitlistEntryRepository $waitlistEntryRepository,
        LoyaltyService $loyaltyService
    ): Response
    {
        $participantEmail = $emailResolver->resolve($request, $this->getUser());
        $typeImages = $this->getFrontEventTypeImages();
        $reservationsCount = 0;
        $popularityScores = [];
        $waitlistEntriesByEvent = [];
        $eventIsFullById = [];
        $eventRemainingPlacesById = [];
        $eventImageUrls = [];
        $favoriteEvents = [];
        $recommendedEvents = [];
        $canAccessVipEvents = false;
        $vipReservationThreshold = $loyaltyService->getVipReservationThreshold();
        $loyaltyStats = ['countConfirmed' => 0, 'totalAmount' => 0.0, 'lastReservationDate' => null];
        $loyaltyTier = 'Bronze';
        $loyaltyScore = 0;
        $loyaltyIdentity = $this->buildEventsLoyaltyIdentity($this->getUser(), $participantEmail);
        $loyaltyAccessLabel = 'VIP access locked';
        $loyaltyMood = 'Discovery mode is well underway';
        $loyaltyBenefitsCopy = 'Priority access locked';
        $loyaltyProgress = ['current' => 0, 'needed' => $vipReservationThreshold, 'nextTier' => 'VIP', 'ratio' => 0.0, 'remaining' => $vipReservationThreshold];
        $heroPulse = ['score' => 0, 'openCount' => 0, 'savedCount' => 0, 'totalCount' => 0];
        $heroSignals = [
            'dnaValue' => '84% match',
            'dnaHint' => 'Recommendation logic aligned this event with your current activity pattern.',
            'energyValue' => 'Explorer mode',
            'energyHint' => 'A flexible match for discovery and broad exploration.',
            'vipPathValue' => $vipReservationThreshold.' steps to VIP',
            'vipPathHint' => 'Connect a member account to activate loyalty memory and VIP tracking.',
        ];
        $featuredEvent = null;

        if (!$this->eventsTablesExist($em)) {
            $this->addFlash('error', 'Events module tables are missing.');
            return $this->render('pages/events.html.twig', [
                'events' => [],
                'selectedEvent' => null,
                'search' => '',
                'sort' => 'asc',
                'types' => [],
                'selectedType' => '',
                'stats' => [],
                'favoriteCounts' => [],
                'favoritedEventIds' => [],
                'reviewStats' => [],
                'selectedEventReviews' => [],
                'selectedReviewSort' => 'recent',
                'mySelectedEventReview' => null,
                'participantEmail' => $participantEmail,
                'reservationsCount' => $reservationsCount,
                'popularityScores' => $popularityScores,
                'waitlistEntriesByEvent' => $waitlistEntriesByEvent,
                'eventIsFullById' => $eventIsFullById,
                'eventRemainingPlacesById' => $eventRemainingPlacesById,
                'eventImageUrls' => $eventImageUrls,
                'favoriteEvents' => $favoriteEvents,
                'recommendedEvents' => $recommendedEvents,
                'canAccessVipEvents' => $canAccessVipEvents,
                'vipReservationThreshold' => $vipReservationThreshold,
                'loyaltyStats' => $loyaltyStats,
                'loyaltyTier' => $loyaltyTier,
                'loyaltyScore' => $loyaltyScore,
                'loyaltyIdentity' => $loyaltyIdentity,
                'loyaltyAccessLabel' => $loyaltyAccessLabel,
                'loyaltyMood' => $loyaltyMood,
                'loyaltyBenefitsCopy' => $loyaltyBenefitsCopy,
                'loyaltyProgress' => $loyaltyProgress,
                'heroPulse' => $heroPulse,
                'heroSignals' => $heroSignals,
                'featuredEvent' => $featuredEvent,
            ]);
        }

        if ($participantEmail !== null) {
            $reservationsCount = $reservationRepository->countByEmail($participantEmail);
            $favoriteEvents = $favoriteRepository->findEventsFavoritedByEmail($participantEmail);
            $canAccessVipEvents = $loyaltyService->isVipEmail($participantEmail);
            $loyaltyStats = $reservationRepository->getLoyaltyStatsForEmail($participantEmail);
            $loyaltyTier = $this->buildEventsLoyaltyTier((int) $loyaltyStats['countConfirmed'], $vipReservationThreshold);
            $loyaltyScore = $this->buildEventsLoyaltyScore((int) $loyaltyStats['countConfirmed'], (float) $loyaltyStats['totalAmount']);
            $loyaltyAccessLabel = $canAccessVipEvents ? 'VIP access active' : 'VIP access locked';
            $loyaltyMood = $this->buildEventsLoyaltyMood($loyaltyTier, $canAccessVipEvents);
            $loyaltyProgress = $this->buildEventsLoyaltyProgress((int) $loyaltyStats['countConfirmed'], $vipReservationThreshold, $canAccessVipEvents);
            $loyaltyBenefitsCopy = $this->buildEventsLoyaltyBenefitsCopy((int) $loyaltyStats['countConfirmed'], $vipReservationThreshold, $canAccessVipEvents);

            $entries = $waitlistEntryRepository->createQueryBuilder('w')
                ->select('w', 'e')
                ->join('w.event', 'e')
                ->andWhere('LOWER(w.email) = :email')
                ->setParameter('email', mb_strtolower($participantEmail))
                ->setMaxResults(150)
                ->getQuery()
                ->getResult();
            foreach ($entries as $entry) {
                $event = $entry->getEvent();
                if ($event instanceof Event && $event->getId() !== null) {
                    $waitlistEntriesByEvent[(int) $event->getId()] = $entry;
                }
            }
        }

        $search = trim((string) $request->query->get('q', ''));
        $selectedType = trim((string) $request->query->get('type', ''));
        $sort = strtolower((string) $request->query->get('sort', 'asc')) === 'desc' ? 'desc' : 'asc';
        $selectedEventId = $request->query->getInt('event', 0);

        $qb = $em->getRepository(Event::class)->createQueryBuilder('e');
        if ($search !== '') {
            $qb->andWhere('e.titre LIKE :q OR e.typeEvent LIKE :q')
                ->setParameter('q', '%'.$search.'%');
        }
        if ($selectedType !== '') {
            $qb->andWhere('e.typeEvent = :type')->setParameter('type', $selectedType);
        }
        $qb->orderBy('e.dateEvent', strtoupper($sort))
            ->setMaxResults(96);
        $events = $qb->getQuery()->getResult();

        $typesRows = $em->getRepository(Event::class)->createQueryBuilder('e')
            ->select('DISTINCT e.typeEvent AS typeEvent')
            ->orderBy('e.typeEvent', 'ASC')
            ->setMaxResults(30)
            ->getQuery()
            ->getArrayResult();
        $types = array_values(array_filter(array_map(static fn (array $row) => (string) $row['typeEvent'], $typesRows)));

        $eventIds = array_values(array_filter(array_map(
            static fn (Event $event): ?int => $event->getId(),
            $events
        )));
        $stats = $this->buildFrontEventStats($em, $eventIds);
        $favoriteCounts = $favoriteRepository->getCountsForEventIds($eventIds);
        $favoritedEventIds = $participantEmail !== null
            ? $favoriteRepository->findFavoritedEventIdsByEmail($participantEmail, $eventIds)
            : [];
        $reviewStats = $eventReviewRepository->getStatsForEvents($eventIds);
        $popularityScores = $reservationRepository->getPopularityScoresForEvents($eventIds);
        $activeReservationsByEvent = $eventCapacityService->countActiveReservationsByEventIds($eventIds);
        foreach ($events as $event) {
            $eventId = (int) ($event->getId() ?? 0);
            if ($eventId <= 0) {
                continue;
            }
            $activeCount = (int) ($activeReservationsByEvent[$eventId] ?? 0);
            $capacity = max(0, (int) $event->getCapacite());
            $eventIsFullById[$eventId] = $activeCount >= $capacity;
            $eventRemainingPlacesById[$eventId] = max(0, $capacity - $activeCount);
            $eventImageUrls[$eventId] = $this->resolveEventImagePublicUrl($event, $typeImages);
        }
        $selectedReviewSort = (string) $request->query->get('review_sort', 'recent');
        if (!in_array($selectedReviewSort, ['recent', 'best'], true)) {
            $selectedReviewSort = 'recent';
        }

        $selectedEvent = null;
        if ($selectedEventId > 0) {
            $selectedEvent = $em->getRepository(Event::class)->find($selectedEventId);
        }

        $selectedEventReviews = [];
        $mySelectedEventReview = null;
        if ($selectedEvent instanceof Event && $selectedEvent->getId() !== null) {
            $selectedEventIdValue = (int) $selectedEvent->getId();
            $selectedEventReviews = $eventReviewRepository->findByEvent($selectedEventIdValue, $selectedReviewSort);
            if ($participantEmail !== null) {
                $mySelectedEventReview = $eventReviewRepository->findOneByEmailAndEvent($participantEmail, $selectedEventIdValue);
            }
        }

        $upcomingEvent = $em->getRepository(Event::class)->createQueryBuilder('e')
            ->where('e.dateEvent >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.dateEvent', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$upcomingEvent && !empty($events)) {
            $upcomingEvent = $events[0];
        }

        $recommendedEvents = $this->buildFrontEventRecommendations(
            $events,
            $favoriteCounts,
            $reviewStats,
            $popularityScores,
            $eventIsFullById,
            $favoritedEventIds,
            $canAccessVipEvents
        );
        $featuredEvent = $recommendedEvents[0]['event'] ?? $upcomingEvent;
        $heroSignals = $this->buildEventsHeroSignals(
            $featuredEvent,
            $recommendedEvents[0]['score'] ?? null,
            (int) $loyaltyStats['countConfirmed'],
            $vipReservationThreshold,
            $canAccessVipEvents,
            count($favoriteEvents)
        );
        $heroPulse = [
            'score' => $loyaltyScore,
            'openCount' => count(array_filter($eventIsFullById, static fn (bool $isFull): bool => $isFull === false)),
            'savedCount' => count($favoriteEvents),
            'totalCount' => count($events),
        ];

        return $this->render('pages/events.html.twig', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'upcomingEvent' => $upcomingEvent,
            'search' => $search,
            'sort' => $sort,
            'types' => $types,
            'selectedType' => $selectedType,
            'stats' => $stats,
            'favoriteCounts' => $favoriteCounts,
            'favoritedEventIds' => $favoritedEventIds,
            'reviewStats' => $reviewStats,
            'selectedEventReviews' => $selectedEventReviews,
            'selectedReviewSort' => $selectedReviewSort,
            'mySelectedEventReview' => $mySelectedEventReview,
            'participantEmail' => $participantEmail,
            'reservationsCount' => $reservationsCount,
            'popularityScores' => $popularityScores,
            'waitlistEntriesByEvent' => $waitlistEntriesByEvent,
            'eventIsFullById' => $eventIsFullById,
            'eventRemainingPlacesById' => $eventRemainingPlacesById,
            'eventImageUrls' => $eventImageUrls,
            'favoriteEvents' => $favoriteEvents,
            'recommendedEvents' => $recommendedEvents,
            'canAccessVipEvents' => $canAccessVipEvents,
            'vipReservationThreshold' => $vipReservationThreshold,
            'loyaltyStats' => $loyaltyStats,
            'loyaltyTier' => $loyaltyTier,
            'loyaltyScore' => $loyaltyScore,
            'loyaltyIdentity' => $loyaltyIdentity,
            'loyaltyAccessLabel' => $loyaltyAccessLabel,
            'loyaltyMood' => $loyaltyMood,
            'loyaltyBenefitsCopy' => $loyaltyBenefitsCopy,
            'loyaltyProgress' => $loyaltyProgress,
            'heroPulse' => $heroPulse,
            'heroSignals' => $heroSignals,
            'featuredEvent' => $featuredEvent,
        ]);
    }

    #[Route('/events/image/{id}', name: 'events_image', methods: ['GET'])]
    public function eventImage(Event $event): Response
    {
        $typeImages = $this->getFrontEventTypeImages();
        $fallback = $typeImages[$event->getTypeEvent()] ?? 'https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?auto=format&fit=crop&w=1400&q=80';
        $imageValue = trim((string) ($event->getImageEvent() ?? ''));

        if ($imageValue === '') {
            return new RedirectResponse($fallback);
        }

        if (preg_match('#^https?://#i', $imageValue) === 1) {
            return new RedirectResponse($imageValue);
        }

        if (preg_match('#^(?:/?uploads/events/)?[a-zA-Z0-9._-]+\.(?:jpg|jpeg|png|webp|gif|avif)$#i', $imageValue) === 1) {
            $filename = basename($imageValue);
            $publicPath = $this->getParameter('kernel.project_dir').'/public/uploads/events/'.$filename;
            if (is_file($publicPath)) {
                return $this->file($publicPath, $filename, ResponseHeaderBag::DISPOSITION_INLINE);
            }

            return new RedirectResponse('/uploads/events/'.$filename);
        }

        $localPath = $this->normalizeEventLocalImagePath($imageValue);
        if ($localPath !== null && is_file($localPath)) {
            $importedUrl = $this->importEventImageToPublic($localPath);
            if ($importedUrl !== null) {
                return new RedirectResponse($importedUrl);
            }

            return $this->file($localPath, basename($localPath), ResponseHeaderBag::DISPOSITION_INLINE);
        }

        return new RedirectResponse($fallback);
    }

    #[Route('/diet-eating-planner', name: 'diet_planner')]
    public function dietPlanner(
        Request $request,
        EntityManagerInterface $em,
        LegacyUserBridgeService $legacyUserBridge,
        JavaDietPlannerService $dietService
    ): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $legacyUserBridge->ensureLegacyMirror($user);
        $activeRegimeId = $request->query->getInt('regime_id', 0);
        $regimeRepo = $em->getRepository(RegimeAlimentaire::class);

        $regime = null;
        if ($activeRegimeId > 0) {
            $regime = $regimeRepo->findOneBy([
                'id' => $activeRegimeId,
                'user' => $user,
            ]);
        }
        if (!$regime) {
            $regime = $regimeRepo->findOneBy(
                ['user' => $user],
                ['id' => 'DESC']
            );
        }

        $regimeForm = null;
        $editForms = [];
        $regimes = $regimeRepo->findBy(
            ['user' => $user],
            ['id' => 'DESC']
        );

        $newRegime = new RegimeAlimentaire();
        $newRegime->setUser($user);
        $createForm = $this->createForm(RegimeAlimentaireFormType::class, $newRegime);
        $createForm->handleRequest($request);
        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $r = $createForm->getData();
            $r->setUser($user);
            $dietService->applyProfile($r);
            $em->persist($r);
            $em->flush();
            $this->addFlash('success', 'Régime créé avec succès.');
            return $this->redirectToRoute('diet_planner');
        }

        foreach ($regimes as $r) {
            $editForms[$r->getId()] = $this->createForm(RegimeAlimentaireFormType::class, $r, [
                'action' => $this->generateUrl('diet_regime_edit', ['id' => $r->getId()]),
            ])->createView();
        }

        $todayStart = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $todayEnd = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);
        $catalogMeals = $regime
            ? $this->buildMealCatalog($em, $user, $regime, $todayStart)
            : [];

        $repasQb = $em->getRepository(Repas::class)->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.dateRepas BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->orderBy('r.dateRepas', 'DESC');

        if ($regime) {
            $repasQb->andWhere('r.regime = :regime')
                ->setParameter('regime', $regime);
        }

        $repas = $repasQb->getQuery()->getResult();

        $totals = [
            'calories' => 0,
            'proteines' => 0,
            'glucides' => 0,
            'lipides' => 0,
        ];
        foreach ($repas as $r) {
            $totals['calories'] += (int) ($r->getCalories() ?? 0);
            $totals['proteines'] += (int) ($r->getProteines() ?? 0);
            $totals['glucides'] += (int) ($r->getGlucides() ?? 0);
            $totals['lipides'] += (int) ($r->getLipides() ?? 0);
        }

        $dailyPlan = [];
        $todayBadges = null;
        $hydrationGoal = JavaDietPlannerService::HYDRATION_GOAL_GLASSES;
        $hydrationCount = $this->getHydrationCount($request, $user, $regime);
        $checklist = [
            'breakfast' => false,
            'lunch' => false,
            'dinner' => false,
            'snack' => false,
            'hydration' => false,
        ];

        if ($regime) {
            $mealRepo = $em->getRepository(Repas::class);
            $today = new \DateTimeImmutable('today');
            $target = (int) ($regime->getCaloriesCibles() ?? 0);
            $lower = $target > 0 ? (int) round($target * 0.9) : 0;
            $upper = $target > 0 ? (int) round($target * 1.1) : 0;
            $weeklyMeals = $mealRepo->createQueryBuilder('r')
                ->where('r.user = :user')
                ->andWhere('r.regime = :regime')
                ->andWhere('r.dateRepas BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('regime', $regime)
                ->setParameter('start', $today->sub(new \DateInterval('P6D'))->setTime(0, 0))
                ->setParameter('end', $today->setTime(23, 59, 59))
                ->orderBy('r.dateRepas', 'ASC')
                ->getQuery()
                ->getResult();
            $weeklyMealsByDay = [];
            foreach ($weeklyMeals as $weeklyMeal) {
                $dateRepas = $weeklyMeal->getDateRepas();
                if (!$dateRepas instanceof \DateTimeInterface) {
                    continue;
                }

                $dayKey = $dateRepas->format('Y-m-d');
                $weeklyMealsByDay[$dayKey] ??= [];
                $weeklyMealsByDay[$dayKey][] = $weeklyMeal;
            }

            for ($i = 6; $i >= 0; $i--) {
                $day = $today->sub(new \DateInterval('P'.$i.'D'));
                $dayMeals = $weeklyMealsByDay[$day->format('Y-m-d')] ?? [];

                $dayTotals = [
                    'calories' => 0,
                    'proteines' => 0,
                    'glucides' => 0,
                    'lipides' => 0,
                ];
                foreach ($dayMeals as $m) {
                    $dayTotals['calories'] += (int) ($m->getCalories() ?? 0);
                    $dayTotals['proteines'] += (int) ($m->getProteines() ?? 0);
                    $dayTotals['glucides'] += (int) ($m->getGlucides() ?? 0);
                    $dayTotals['lipides'] += (int) ($m->getLipides() ?? 0);
                }

                $hasMeals = \count($dayMeals) > 0;
                $caloriesOk = $target > 0 && $hasMeals && $dayTotals['calories'] >= $lower && $dayTotals['calories'] <= $upper;
                $hydrationOk = $i === 0
                    ? $hydrationCount >= $hydrationGoal
                    : $hasMeals && \count($dayMeals) >= 3;

                $dailyPlan[] = [
                    'date' => $day,
                    'meals' => $dayMeals,
                    'totals' => $dayTotals,
                    'badges' => [
                        'calories_ok' => $caloriesOk,
                        'hydration_ok' => $hydrationOk,
                    ],
                ];

                if ($i === 0) {
                    $todayBadges = [
                        'calories_ok' => $caloriesOk,
                        'hydration_ok' => $hydrationOk,
                        'totals' => $dayTotals,
                        'hydration_count' => $hydrationCount,
                        'hydration_goal' => $hydrationGoal,
                    ];
                }
            }
        }

        $hasBreakfast = false;
        $hasLunch = false;
        $hasDinner = false;
        $hasSnack = false;
        foreach ($repas as $meal) {
            $type = strtolower($meal->getTypeRepas());
            if (str_contains($type, 'petit') || str_contains($type, 'breakfast')) {
                $hasBreakfast = true;
            } elseif (str_contains($type, 'déj') || str_contains($type, 'dej') || str_contains($type, 'lunch')) {
                $hasLunch = true;
            } elseif (str_contains($type, 'dîner') || str_contains($type, 'diner') || str_contains($type, 'dinner') || str_contains($type, 'soir')) {
                $hasDinner = true;
            } else {
                $hasSnack = true;
            }
        }
        $checklist['breakfast'] = $hasBreakfast;
        $checklist['lunch'] = $hasLunch;
        $checklist['dinner'] = $hasDinner;
        $checklist['snack'] = $hasSnack;
        $checklist['hydration'] = $hydrationCount >= $hydrationGoal;

        $remainingCalories = max(0, (int) ($regime?->getCaloriesCibles() ?? 0) - $totals['calories']);
        $calorieProgress = $regime && $regime->getCaloriesCibles()
            ? min(100, (int) round(($totals['calories'] / $regime->getCaloriesCibles()) * 100))
            : 0;
        $suggestionLine = $dietService->buildSuggestionLine($regime, $repas, $totals);
        $shoppingList = $dietService->buildShoppingList($repas, $totals);
        $achievements = $dietService->buildAchievements($regime, $totals, $repas, $hydrationCount, $todayBadges);

        return $this->render('pages/diet-planner.html.twig', [
            'regime' => $regime,
            'regimes' => $regimes,
            'regimeCreateForm' => $createForm->createView(),
            'regimeEditForms' => $editForms,
            'repas' => $repas,
            'catalogMeals' => $catalogMeals,
            'totals' => $totals,
            'dailyPlan' => $dailyPlan,
            'todayBadges' => $todayBadges,
            'checklist' => $checklist,
            'hydrationCount' => $hydrationCount,
            'hydrationGoal' => $hydrationGoal,
            'remainingCalories' => $remainingCalories,
            'calorieProgress' => $calorieProgress,
            'suggestionLine' => $suggestionLine,
            'shoppingPreview' => $shoppingList,
            'achievements' => $achievements,
            'dietHelper' => $dietService,
            'showCreateRegimeOnboarding' => count($regimes) === 0,
        ]);
    }

    #[Route('/diet-eating-planner/regime/{id}/edit', name: 'diet_regime_edit', methods: ['POST'])]
    public function editRegime(
        RegimeAlimentaire $regime,
        Request $request,
        EntityManagerInterface $em,
        JavaDietPlannerService $dietService
    ): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User || $regime->getUser()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('diet_planner');
        }

        $form = $this->createForm(RegimeAlimentaireFormType::class, $regime);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $r = $form->getData();
            $dietService->applyProfile($r);
            $em->flush();
            $this->addFlash('success', 'Régime mis à jour.');
            return $this->redirectToRoute('diet_planner');
        }
        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/diet-eating-planner/regime/{id}/delete', name: 'diet_regime_delete', methods: ['POST'])]
    public function deleteRegime(RegimeAlimentaire $regime, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User || $regime->getUser()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('diet_planner');
        }

        if (!$this->isCsrfTokenValid('regime_delete_'.$regime->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('diet_planner');
        }

        $meals = $em->getRepository(Repas::class)->findBy(['regime' => $regime]);
        foreach ($meals as $m) {
            $m->setRegime(null);
        }
        $em->remove($regime);
        $em->flush();
        $this->addFlash('success', 'Régime supprimé.');
        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/diet-eating-planner/meal/add', name: 'diet_meal_add', methods: ['POST'])]
    public function addMeal(
        Request $request,
        EntityManagerInterface $em,
        LegacyUserBridgeService $legacyUserBridge
    ): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        $legacyUserBridge->ensureLegacyMirror($user);

        if (!$this->isCsrfTokenValid('meal_add', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('diet_planner');
        }

        $requestedRegimeId = $request->request->getInt('regime_id', 0);
        $regime = null;
        if ($requestedRegimeId > 0) {
            $regime = $em->getRepository(RegimeAlimentaire::class)->findOneBy([
                'id' => $requestedRegimeId,
                'user' => $user,
            ]);
        }
        if (!$regime) {
            $regime = $em->getRepository(RegimeAlimentaire::class)->findOneBy(
                ['user' => $user],
                ['id' => 'DESC']
            );
        }

        $sourceId = $request->request->getInt('source_repas_id') ?: null;
        $source = null;
        $calories = 0;
        if ($sourceId) {
            /** @var Repas|null $source */
            $source = $em->getRepository(Repas::class)->find($sourceId);
            if (!$source) {
                $this->addFlash('error', 'Repas sélectionné invalide.');
                return $this->redirectToRoute('diet_planner');
            }
            $calories = (int) ($source->getCalories() ?? 0);
        } else {
            $calories = $request->request->getInt('calories') ?: 0;
        }

        $currentTotal = 0;
        $wouldExceedTarget = false;

        if ($regime && $regime->getCaloriesCibles()) {
            $todayStart = (new \DateTimeImmutable('today'))->setTime(0, 0);
            $todayEnd = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);

            $qb = $em->getRepository(Repas::class)->createQueryBuilder('r')
                ->select('COALESCE(SUM(r.calories), 0)')
                ->where('r.user = :user')
                ->andWhere('r.dateRepas BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $todayStart)
                ->setParameter('end', $todayEnd);

            $currentTotal = (int) $qb->getQuery()->getSingleScalarResult();
            $wouldExceedTarget = ($currentTotal + $calories) > (int) $regime->getCaloriesCibles();
        }

        $meal = new Repas();
        $meal->setUser($user);

         if ($source instanceof Repas) {
            $meal->setTypeRepas($source->getTypeRepas());
            $meal->setNomRepas($source->getNomRepas());
            $meal->setCalories($source->getCalories());
            $meal->setProteines($source->getProteines());
            $meal->setGlucides($source->getGlucides());
            $meal->setLipides($source->getLipides());
            $meal->setCommentaire($source->getCommentaire());
        } else {
            $typeRepas = trim((string) $request->request->get('type_repas'));
            $nomRepas = trim((string) $request->request->get('nom_repas'));
            if ($typeRepas === '' || $nomRepas === '') {
                $this->addFlash('error', 'Type de repas et nom du repas sont obligatoires.');
                if ($requestedRegimeId > 0) {
                    return $this->redirectToRoute('diet_planner', ['regime_id' => $requestedRegimeId]);
                }
                return $this->redirectToRoute('diet_planner');
            }
            $meal->setTypeRepas($typeRepas);
            $meal->setNomRepas($nomRepas);
            $protein = $request->request->getInt('proteines') ?: null;
            $carbs = $request->request->getInt('glucides') ?: null;
            $fat = $request->request->getInt('lipides') ?: null;
            if ($calories <= 0) {
                $calories = (max(0, (int) $protein) * 4) + (max(0, (int) $carbs) * 4) + (max(0, (int) $fat) * 9);
            }
            $meal->setCalories($calories ?: null);
            $meal->setProteines($protein);
            $meal->setGlucides($carbs);
            $meal->setLipides($fat);
            $meal->setCommentaire((string) $request->request->get('commentaire') ?: null);
        }

        $meal->setRegime($regime);

        try {
            $em->persist($meal);
            $em->flush();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Echec enregistrement repas (BDD): '.$e->getMessage());
            if ($requestedRegimeId > 0) {
                return $this->redirectToRoute('diet_planner', ['regime_id' => $requestedRegimeId]);
            }
            return $this->redirectToRoute('diet_planner');
        }

        $this->addFlash('success', 'Repas ajoute avec succes (ID: '.$meal->getId().').');

        if ($wouldExceedTarget) {
            $this->addFlash('warning', 'Repas ajoute, mais vous depassez la cible calorique du jour.');
        }

        if ($requestedRegimeId > 0) {
            return $this->redirectToRoute('diet_planner', ['regime_id' => $requestedRegimeId]);
        }
        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/diet-eating-planner/meal/{id}/update', name: 'diet_meal_update', methods: ['POST'])]
    public function updateMeal(Repas $meal, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User || $meal->getUser()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('diet_planner');
        }

        if (!$this->isCsrfTokenValid('meal_update_'.$meal->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('diet_planner');
        }

        $meal->setTypeRepas((string) $request->request->get('type_repas'));
        $meal->setNomRepas((string) $request->request->get('nom_repas'));
        $protein = $request->request->getInt('proteines') ?: null;
        $carbs = $request->request->getInt('glucides') ?: null;
        $fat = $request->request->getInt('lipides') ?: null;
        $calories = $request->request->getInt('calories') ?: 0;
        if ($calories <= 0) {
            $calories = (max(0, (int) $protein) * 4) + (max(0, (int) $carbs) * 4) + (max(0, (int) $fat) * 9);
        }
        $meal->setCalories($calories ?: null);
        $meal->setProteines($protein);
        $meal->setGlucides($carbs);
        $meal->setLipides($fat);
        $meal->setCommentaire((string) $request->request->get('commentaire') ?: null);
        $em->flush();

        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/diet-eating-planner/meal/{id}/delete', name: 'diet_meal_delete', methods: ['POST'])]
    public function deleteMeal(Repas $meal, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User || $meal->getUser()?->getId() !== $user->getId()) {
            return $this->redirectToRoute('diet_planner');
        }

        if (!$this->isCsrfTokenValid('meal_delete_'.$meal->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('diet_planner');
        }

        $em->remove($meal);
        $em->flush();
        return $this->redirectToRoute('diet_planner');
    }

    #[Route('/diet-eating-planner/hydration/add', name: 'diet_hydration_add', methods: ['POST'])]
    public function addHydrationGlass(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Authentication required.'], 401);
        }

        if (!$this->isCsrfTokenValid('diet_hydration_add', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'message' => 'Invalid security token.'], 403);
        }

        $regime = $em->getRepository(RegimeAlimentaire::class)->findOneBy(
            ['user' => $user],
            ['id' => 'DESC']
        );

        $session = $request->getSession();
        $key = $this->hydrationSessionKey($user, $regime);
        $count = (int) $session->get($key, 0);
        $count = min(JavaDietPlannerService::HYDRATION_GOAL_GLASSES, $count + 1);
        $session->set($key, $count);

        return $this->json([
            'ok' => true,
            'count' => $count,
            'goal' => JavaDietPlannerService::HYDRATION_GOAL_GLASSES,
            'unlocked' => $count >= JavaDietPlannerService::HYDRATION_GOAL_GLASSES,
        ]);
    }

    #[Route('/diet-eating-planner/shopping-list', name: 'diet_shopping_list', methods: ['GET'])]
    public function shoppingList(
        Request $request,
        EntityManagerInterface $em,
        JavaDietPlannerService $dietService
    ): JsonResponse {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->json(['items' => [], 'totals' => ['calories' => 0, 'proteines' => 0, 'glucides' => 0, 'lipides' => 0]], 401);
        }

        $regime = $em->getRepository(RegimeAlimentaire::class)->findOneBy(
            ['user' => $user],
            ['id' => 'DESC']
        );

        $todayStart = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $todayEnd = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);
        $qb = $em->getRepository(Repas::class)->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.dateRepas BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->orderBy('r.dateRepas', 'DESC');

        if ($regime) {
            $qb->andWhere('r.regime = :regime')->setParameter('regime', $regime);
        }

        $repas = $qb->getQuery()->getResult();
        $totals = ['calories' => 0, 'proteines' => 0, 'glucides' => 0, 'lipides' => 0];
        foreach ($repas as $meal) {
            $totals['calories'] += (int) ($meal->getCalories() ?? 0);
            $totals['proteines'] += (int) ($meal->getProteines() ?? 0);
            $totals['glucides'] += (int) ($meal->getGlucides() ?? 0);
            $totals['lipides'] += (int) ($meal->getLipides() ?? 0);
        }

        return $this->json($dietService->buildShoppingList($repas, $totals));
    }

    #[Route('/diet-eating-planner/barcode-lookup', name: 'diet_barcode_lookup', methods: ['GET'])]
    public function barcodeLookup(Request $request): JsonResponse
    {
        $barcode = preg_replace('/\D+/', '', (string) $request->query->get('barcode', ''));
        if (!is_string($barcode) || $barcode === '' || strlen($barcode) < 8) {
            return $this->json(['ok' => false, 'message' => 'Barcode invalide.'], 400);
        }

        $url = 'https://world.openfoodfacts.org/api/v2/product/'.$barcode.'.json';
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'User-Agent: FitopiaApp - JavaFX - Version 1.0 - https://github.com/esprit-pidev/Fitopia',
                ]),
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return $this->json(['ok' => false, 'message' => 'OpenFoodFacts indisponible.'], 502);
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || (int) ($data['status'] ?? 0) !== 1) {
            return $this->json(['ok' => false, 'message' => 'Produit introuvable.'], 404);
        }

        $product = is_array($data['product'] ?? null) ? $data['product'] : [];
        $nutriments = is_array($product['nutriments'] ?? null) ? $product['nutriments'] : [];

        $food = trim((string) ($product['product_name'] ?? $product['product_name_fr'] ?? $product['generic_name'] ?? 'Produit scanne'));
        $brands = trim((string) ($product['brands'] ?? ''));
        $categories = trim((string) ($product['categories'] ?? ''));
        $nutriScore = trim((string) ($product['nutriscore_grade'] ?? ''));

        $commentParts = array_filter([
            $brands !== '' ? 'Marque: '.$brands : null,
            $categories !== '' ? 'Categories: '.$categories : null,
            $nutriScore !== '' ? 'Nutri-Score: '.strtoupper($nutriScore) : null,
        ]);

        return $this->json([
            'ok' => true,
            'food' => $food !== '' ? $food : 'Produit scanne',
            'calories' => (int) round((float) ($nutriments['energy-kcal_100g'] ?? $nutriments['energy-kcal_serving'] ?? 0)),
            'protein' => (int) round((float) ($nutriments['proteins_100g'] ?? $nutriments['proteins_serving'] ?? 0)),
            'carbs' => (int) round((float) ($nutriments['carbohydrates_100g'] ?? $nutriments['carbohydrates_serving'] ?? 0)),
            'fat' => (int) round((float) ($nutriments['fat_100g'] ?? $nutriments['fat_serving'] ?? 0)),
            'comment' => implode(' | ', $commentParts),
        ]);
    }

    #[Route('/diet-eating-planner/scan-analyze', name: 'diet_scan_analyze', methods: ['POST'])]
    public function scanAnalyze(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('diet_scan_analyze', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid security token.'], 403);
        }

        /** @var UploadedFile|null $image */
        $image = $request->files->get('image');
        if (!$image instanceof UploadedFile) {
            return $this->json(['error' => 'Image requise.'], 400);
        }

        $apiKey = trim((string) ($_ENV['API_KEY_SPOONACULAR'] ?? $_SERVER['API_KEY_SPOONACULAR'] ?? ''));
        if ($apiKey !== '' && function_exists('curl_init')) {
            $response = $this->callSpoonacularImageAnalyze($image, $apiKey);
            if ($response !== null) {
                return $this->json($response);
            }
        }

        $filename = mb_strtolower($image->getClientOriginalName());
        $profiles = [
            ['keys' => ['salad', 'salade'], 'food' => 'Salade composee', 'calories' => 320, 'protein' => 12, 'carbs' => 18, 'fat' => 18],
            ['keys' => ['poulet', 'chicken'], 'food' => 'Poulet grille et legumes', 'calories' => 430, 'protein' => 36, 'carbs' => 24, 'fat' => 18],
            ['keys' => ['saumon', 'salmon', 'fish', 'poisson'], 'food' => 'Saumon et legumes', 'calories' => 460, 'protein' => 34, 'carbs' => 12, 'fat' => 28],
            ['keys' => ['pasta', 'pates', 'pate'], 'food' => 'Pates completes', 'calories' => 510, 'protein' => 18, 'carbs' => 68, 'fat' => 15],
        ];

        $selected = ['food' => 'Repas equilibre', 'calories' => 420, 'protein' => 24, 'carbs' => 34, 'fat' => 16];
        foreach ($profiles as $profile) {
            foreach ($profile['keys'] as $keyword) {
                if (str_contains($filename, $keyword)) {
                    $selected = $profile;
                    break 2;
                }
            }
        }

        return $this->json($selected + ['confidence' => 61]);
    }

    #[Route('/diet-eating-planner/pdf', name: 'diet_pdf_export', methods: ['GET'])]
    public function exportDietPdf(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $regime = $em->getRepository(RegimeAlimentaire::class)->findOneBy(
            ['user' => $user],
            ['id' => 'DESC']
        );
        $todayStart = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $todayEnd = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);
        $repas = $em->getRepository(Repas::class)->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.dateRepas BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->orderBy('r.dateRepas', 'ASC')
            ->getQuery()
            ->getResult();

        $html = '<html><head><meta charset="UTF-8"><style>';
        $html .= 'body{font-family:Helvetica,Arial,sans-serif;color:#0f172a;font-size:12px;line-height:1.5;}';
        $html .= 'h1{font-size:22px;margin:0 0 12px;}';
        $html .= 'h2{font-size:16px;margin:18px 0 10px;}';
        $html .= 'p{margin:0 0 8px;}';
        $html .= 'ul{padding-left:18px;margin:8px 0 0;}';
        $html .= 'li{margin-bottom:6px;}';
        $html .= '</style></head><body>';
        $html .= '<h1>Fitopia - Diet & Eating Planner</h1>';
        $html .= '<p>Utilisateur: '.htmlspecialchars((string) $user->getDisplayName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        if ($regime instanceof RegimeAlimentaire) {
            $html .= '<p>Regime: '.htmlspecialchars((string) ($regime->getTypeSante() ?? 'N/A'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').' - '.(int) ($regime->getCaloriesCibles() ?? 0).' kcal/j</p>';
        }
        $html .= '<h2>Repas du jour</h2><ul>';
        foreach ($repas as $meal) {
            $html .= '<li>'.htmlspecialchars(
                $meal->getTypeRepas().' - '.$meal->getNomRepas().' ('.(int) ($meal->getCalories() ?? 0).' kcal)',
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ).'</li>';
        }
        $html .= '</ul></body></html>';

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="fitopia-diet-planner.pdf"',
        ]);
    }

    #[Route('/explore', name: 'explore_forums_blogs')]
    public function explore(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response
    {
        $user = $this->currentUser();
        $connection = $em->getConnection();
        $this->ensureForumFeedSchema($connection);

        $activeExploreView = (string) $request->query->get('view', 'feed');
        if (!in_array($activeExploreView, ['feed', 'reels'], true)) {
            $activeExploreView = 'feed';
        }
        $shouldLoadFeed = $activeExploreView === 'feed';
        $feedLimit = 18;

        $editingPost = null;
        $editingPostId = max(0, $request->query->getInt('edit'));
        if ($editingPostId > 0 && $user instanceof User) {
            $candidate = $em->getRepository(BlogPost::class)->find($editingPostId);
            if ($candidate instanceof BlogPost && $candidate->getAuthor()?->getId() === $user->getId()) {
                $editingPost = $candidate;
            }
        }

        $savedOnly = $request->query->getBoolean('saved');
        $post = $editingPost ?? new BlogPost();
        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$user instanceof User) {
                $this->addFlash('error', 'Please login to publish a post.');
                return $this->redirectToRoute('app_login');
            }

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
            } elseif ($request->request->getBoolean('remove_image')) {
                $post->setImagePath(null);
            }

            if (!$editingPost instanceof BlogPost) {
                $post->setAuthor($user);
                $post->setCreatedAt(new \DateTimeImmutable());
            }

            $em->persist($post);
            $em->flush();

            $this->addFlash('success', $editingPost instanceof BlogPost ? 'Your post has been updated.' : 'Your post has been published.');
            return $this->redirectToRoute('explore_forums_blogs', $savedOnly ? ['saved' => 1] : []);
        }

        $posts = [];
        $savedPostIds = [];
        $postIds = [];
        $interactionMap = [];
        $currentEmail = $user instanceof User ? mb_strtolower($user->getEmail()) : null;

        if ($shouldLoadFeed) {
            if ($savedOnly && $user instanceof User) {
                $savedPostIds = array_slice(array_map(
                    'intval',
                    $connection->fetchFirstColumn(
                        'SELECT forum_id FROM forum_saves WHERE user_id = :user ORDER BY id DESC',
                        ['user' => $user->getId()]
                    )
                ), 0, $feedLimit);

                if ($savedPostIds !== []) {
                    $postsById = $em->getRepository(BlogPost::class)->findBy(['id' => $savedPostIds]);
                    $indexedPosts = [];
                    foreach ($postsById as $savedPost) {
                        if ($savedPost->getId() !== null) {
                            $indexedPosts[$savedPost->getId()] = $savedPost;
                        }
                    }
                    foreach ($savedPostIds as $savedId) {
                        if (isset($indexedPosts[$savedId])) {
                            $posts[] = $indexedPosts[$savedId];
                        }
                    }
                }
            } else {
                $posts = $em->getRepository(BlogPost::class)
                    ->createQueryBuilder('p')
                    ->orderBy('p.createdAt', 'DESC')
                    ->setMaxResults($feedLimit)
                    ->getQuery()
                    ->getResult();
            }

            $postIds = array_values(array_filter(array_map(static fn (BlogPost $blogPost): int => (int) ($blogPost->getId() ?? 0), $posts)));
            $interactionMap = $this->buildForumInteractionMap($connection, $postIds);
            $savedPostIds = $user instanceof User ? $this->fetchSavedForumIds($connection, (int) $user->getId(), $postIds) : [];
        }

        $items = [];
        foreach ($posts as $p) {
            $author = $p->getAuthor();
            $role = $author?->getRole() ?? 'Patient';
            $stats = $interactionMap[$p->getId()] ?? ['likes' => [], 'reposts' => [], 'comments' => []];
            $likedByMe = false;
            $repostedByMe = false;
            if ($currentEmail !== null) {
                $likedByMe = in_array($currentEmail, array_map('mb_strtolower', $stats['likes']), true);
                $repostedByMe = in_array($currentEmail, array_map('mb_strtolower', $stats['reposts']), true);
            }

            $displayEmail = $author?->getEmail() ?? '';
            $displayName = $author?->getDisplayName() ?? ($displayEmail !== '' ? $displayEmail : 'Fitopia member');
            $displayDate = $p->getCreatedAt()->format('Y-m-d');
            $postId = (int) ($p->getId() ?? 0);
            $items[] = [
                'id' => $postId,
                'title' => $p->getTitle(),
                'excerpt' => $p->getExcerpt() ?? '',
                'content' => $p->getContent(),
                'authorName' => $displayName,
                'email' => $displayEmail,
                'avatar' => $author?->getAvatarPath(),
                'role' => $role,
                'category' => $p->getCategory(),
                'image' => $this->resolveForumImagePublicUrl($p->getImagePath()),
                'date' => $displayDate,
                'likes' => [
                    'count' => count($stats['likes']),
                    'users' => $stats['likes'],
                ],
                'reposts' => [
                    'count' => count($stats['reposts']),
                    'users' => $stats['reposts'],
                ],
                'comments' => [
                    'count' => count($stats['comments']),
                    'items' => $stats['comments'],
                ],
                'likedByMe' => $likedByMe,
                'repostedByMe' => $repostedByMe,
                'savedByMe' => in_array($postId, $savedPostIds, true),
                'ownedByMe' => $user instanceof User && $author?->getId() === $user->getId(),
            ];
        }

        $todayStart = new \DateTimeImmutable('today');
        $tomorrowStart = $todayStart->modify('+1 day');
        $communityActiveCount = $this->countActiveCommunityUsers($connection, $todayStart, $tomorrowStart);

        $userPosts = [];
        if ($shouldLoadFeed && $user instanceof User) {
            $userPosts = $em->getRepository(BlogPost::class)->findBy(
                ['author' => $user],
                ['createdAt' => 'DESC'],
                8
            );
        }

        $dmConversations = [];
        $dmConversationItems = [];
        $dmSelectedConversation = null;
        $dmMessages = [];
        $dmOtherParticipant = null;
        $dmOtherBadge = null;
        $dmOtherName = null;
        $dmStatusLine = null;
        $dmUnreadCounts = [];
        $dmLastMessage = [];
        $dmLastMessageTime = [];
        $dmUsers = [];
        $dmUnreadTotal = 0;
        $dmUnreadSenders = [];
        $forumNotifications = [];
        $forumNotificationUnread = 0;

        if ($user instanceof User) {
            $forumNotifications = $this->fetchForumNotifications($connection, (int) $user->getId());
            $forumNotificationUnread = count(array_filter(
                $forumNotifications,
                static fn (array $notification): bool => !$notification['isRead']
            ));

            $dmConversations = $em->createQueryBuilder()
                ->select('c', 'a', 'b')
                ->from(DmConversation::class, 'c')
                ->leftJoin('c.userA', 'a')
                ->leftJoin('c.userB', 'b')
                ->where('a = :me OR b = :me')
                ->setParameter('me', $user)
                ->orderBy('c.lastMessageAt', 'DESC')
                ->getQuery()
                ->getResult();

            $conversationById = [];
            foreach ($dmConversations as $conversation) {
                if ($conversation->getId() !== null) {
                    $conversationById[$conversation->getId()] = $conversation;
                }
            }

            $selectedId = (int) $request->query->get('dm', 0);
            if ($selectedId > 0 && isset($conversationById[$selectedId])) {
                $dmSelectedConversation = $conversationById[$selectedId];
            }

            $knownUserIds = [];
            $conversationIds = [];
            foreach ($dmConversations as $conv) {
                $other = $this->getOtherConversationUser($conv, $user);

                if ($other instanceof User) {
                    if ($other->getId() !== null) {
                        $knownUserIds[] = (int) $other->getId();
                    }
                    $dmConversationItems[] = [
                        'id' => $conv->getId(),
                        'otherId' => $other->getId(),
                        'otherEmail' => $other->getEmail(),
                        'displayName' => $other->getDisplayName(),
                    ];
                }

                $conversationId = $conv->getId();
                if ($conversationId === null) {
                    continue;
                }
                $conversationIds[] = (int) $conversationId;
            }

            $conversationIds = array_values(array_unique($conversationIds));
            $knownUserIds = array_values(array_unique(array_filter($knownUserIds, static fn (int $id): bool => $id > 0)));

            if ($conversationIds !== []) {
                $dmUnreadCounts = $this->fetchUnreadCounts(
                    $connection,
                    $conversationIds,
                    (int) $user->getId()
                );
                [$dmLastMessage, $dmLastMessageTime] = $this->fetchConversationLastMessages($connection, $conversationIds);
            }

            foreach ($dmConversations as $conv) {
                $conversationId = $conv->getId();
                $other = $this->getOtherConversationUser($conv, $user);
                if ($conversationId === null) {
                    continue;
                }

                $unreadCount = $dmUnreadCounts[$conversationId] ?? 0;
                $dmUnreadTotal += $unreadCount;
                if ($other instanceof User && $unreadCount > 0) {
                    $senderKey = mb_strtolower($other->getEmail());
                    if (!isset($dmUnreadSenders[$senderKey])) {
                        $dmUnreadSenders[$senderKey] = [
                            'id' => $other->getId(),
                            'name' => $other->getDisplayName(),
                            'email' => $other->getEmail(),
                            'avatar' => $other->getAvatarPath(),
                        ];
                    }
                }
            }

            if ($dmSelectedConversation) {
                $dmMessages = $em->getRepository(DmMessage::class)
                    ->findBy(['conversation' => $dmSelectedConversation], ['createdAt' => 'ASC']);

                $dmOtherParticipant = $this->getOtherConversationUser($dmSelectedConversation, $user);
                if ($dmOtherParticipant instanceof User) {
                    $dmOtherBadge = $dmOtherParticipant->getRole();
                    $dmOtherName = $dmOtherParticipant->getDisplayName();
                }

                foreach ($dmMessages as $message) {
                    if ($message->getReceiver()?->getId() === $user->getId() && !$message->isRead()) {
                        $message->setIsRead(true);
                    }
                }

                $lastMessage = !empty($dmMessages) ? $dmMessages[array_key_last($dmMessages)] : null;
                if ($lastMessage instanceof DmMessage) {
                    if ($lastMessage->getSender()?->getId() === $user->getId()) {
                        $dmStatusLine = 'Sent '.$this->formatAgo($lastMessage->getCreatedAt());
                    } else {
                        $dmStatusLine = 'Received '.$this->formatAgo($lastMessage->getCreatedAt());
                    }
                } else {
                    $dmStatusLine = 'Active conversation';
                }

                $em->flush();
            }

            $dmUsersQb = $em->getRepository(User::class)
                ->createQueryBuilder('u')
                ->where('u != :me')
                ->setParameter('me', $user)
                ->orderBy('u.id', 'DESC')
                ->setMaxResults(18);

            if ($knownUserIds !== []) {
                $dmUsersQb
                    ->andWhere('u.id NOT IN (:knownUserIds)')
                    ->setParameter('knownUserIds', $knownUserIds);
            }

            $dmUsers = $dmUsersQb->getQuery()->getResult();
        }

        $reelsFeed = [
            [
                'title' => 'Chest Builder Machine',
                'author' => 'Fitopia Coach',
                'caption' => 'Controlled chest press to activate the pecs and front delts.',
                'video' => 'videos/fitness/developpe-incline-machine.mp4',
                'likes' => 124,
                'comments' => 19,
            ],
            [
                'title' => 'Full Abs Circuit',
                'author' => 'Healthy Core Lab',
                'caption' => 'Short abs session to wake up the core and keep the rhythm high.',
                'video' => 'videos/fitness/local/abdos-complet-20min.mp4',
                'likes' => 212,
                'comments' => 34,
            ],
            [
                'title' => 'Arm Fly Focus',
                'author' => 'Upper Body Team',
                'caption' => 'Chest fly variation with a clean tempo and stronger squeeze.',
                'video' => 'videos/fitness/arm-chest-flyes-poulie.mp4',
                'likes' => 96,
                'comments' => 12,
            ],
            [
                'title' => 'Back Home Session',
                'author' => 'Fitopia Reels',
                'caption' => 'Quick at-home back routine for posture, pull strength and control.',
                'video' => 'videos/fitness/local/back-home-5min.mp4',
                'likes' => 175,
                'comments' => 21,
            ],
        ];

        return $this->render('pages/explore.html.twig', [
            'items' => $items,
            'blogForm' => $form->createView(),
            'userPosts' => $userPosts,
            'dmConversations' => $dmConversations,
            'dmConversationItems' => $dmConversationItems,
            'dmSelectedConversation' => $dmSelectedConversation,
            'dmMessages' => $dmMessages,
            'dmOtherParticipant' => $dmOtherParticipant,
            'dmOtherBadge' => $dmOtherBadge,
            'dmOtherName' => $dmOtherName,
            'dmStatusLine' => $dmStatusLine,
            'dmUnreadCounts' => $dmUnreadCounts,
            'dmLastMessage' => $dmLastMessage,
            'dmLastMessageTime' => $dmLastMessageTime,
            'dmUsers' => $dmUsers,
            'dmUnreadTotal' => $dmUnreadTotal,
            'dmUnreadSenders' => array_values($dmUnreadSenders),
            'communityActiveCount' => $communityActiveCount,
            'forumNotifications' => $forumNotifications,
            'forumNotificationUnread' => $forumNotificationUnread,
            'savedOnly' => $savedOnly,
            'editingPost' => $editingPost,
            'activeExploreView' => $activeExploreView,
            'reelsFeed' => $reelsFeed,
        ]);
    }

    #[Route('/explore/{id}/delete', name: 'explore_post_delete', methods: ['POST'])]
    public function deleteExplorePost(
        BlogPost $post,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_post_'.$post->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        if ($post->getAuthor()?->getId() !== $user->getId()) {
            $this->addFlash('error', 'Only the author can delete this post.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post deleted.');
        return $this->redirectToRoute('explore_forums_blogs');
    }

    #[Route('/explore/messages/{id}/send', name: 'explore_dm_send', methods: ['POST'])]
    public function sendDmMessage(
        DmConversation $conversation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $receiver = $this->getOtherConversationUser($conversation, $user);
        if (!$receiver instanceof User) {
            $this->addFlash('error', 'Conversation unavailable.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $body = trim((string) $request->request->get('body'));
        /** @var UploadedFile|null $attachment */
        $attachment = $request->files->get('attachment');
        if ($body === '' && !$attachment) {
            return $this->redirectToRoute('explore_forums_blogs', ['dm' => $conversation->getId()]);
        }

        $message = new DmMessage();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setReceiver($receiver);
        $message->setBody($body !== '' ? $body : '[Attachment]');
        $message->setStatus('sent');

        if ($attachment) {
            $projectDir = $this->getParameter('kernel.project_dir');
            if (!is_string($projectDir)) {
                throw new \RuntimeException('Invalid project directory.');
            }
            $uploadDir = $projectDir.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'dm';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $originalName = pathinfo($attachment->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
            $newName = $safeName.'-'.uniqid().'.'.$this->safeUploadedFileExtension($attachment, 'bin');

            try {
                $attachment->move($uploadDir, $newName);
                $message->setAttachmentPath('uploads/dm/'.$newName);
                $message->setAttachmentMime($this->safeUploadedFileMimeType($attachment));
            } catch (FileException $e) {
                // fallback: no attachment saved
            }
        }

        $conversation->setLastMessageAt(new \DateTimeImmutable());

        $em->persist($message);
        $em->flush();

        return $this->redirectToRoute('explore_forums_blogs', ['dm' => $conversation->getId()]);
    }

    #[Route('/explore/messages/{id}/poll', name: 'explore_dm_poll', methods: ['GET'])]
    public function pollDmMessages(
        DmConversation $conversation,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        if (!$this->conversationIncludesUser($conversation, $user)) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        $messages = $em->getRepository(DmMessage::class)
            ->findBy(['conversation' => $conversation], ['createdAt' => 'ASC']);

        foreach ($messages as $m) {
            if ($m->getReceiver()?->getId() === $user->getId() && !$m->isRead()) {
                $m->setIsRead(true);
            }
        }
        $em->flush();

        $payload = array_map(function (DmMessage $m) use ($user): array {
            return [
                'id' => $m->getId(),
                'body' => $m->getBody(),
                'createdAt' => $m->getCreatedAt()->format('H:i'),
                'isMine' => $m->getSender()?->getId() === $user->getId(),
                'status' => $m->getStatus(),
                'attachment' => $m->getAttachmentPath(),
                'attachmentMime' => $m->getAttachmentMime(),
            ];
        }, $messages);

        return new JsonResponse([
            'messages' => $payload,
            'typing' => false,
        ]);
    }

    #[Route('/explore/messages/{id}/typing', name: 'explore_dm_typing', methods: ['POST'])]
    public function typingDm(
        DmConversation $conversation,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        if (!$this->conversationIncludesUser($conversation, $user)) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function getHydrationCount(Request $request, User $user, ?RegimeAlimentaire $regime): int
    {
        return (int) $request->getSession()->get($this->hydrationSessionKey($user, $regime), 0);
    }

    /**
     * @return Repas[]
     */
    private function buildMealCatalog(
        EntityManagerInterface $em,
        User $user,
        ?RegimeAlimentaire $regime,
        \DateTimeImmutable $todayStart
    ): array {
        if (!$regime) {
            return [];
        }

        /** @var Repas[] $history */
        $history = $em->getRepository(Repas::class)->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.regime = :regime')
            ->andWhere('r.dateRepas < :todayStart')
            ->setParameter('user', $user)
            ->setParameter('regime', $regime)
            ->setParameter('todayStart', $todayStart)
            ->orderBy('r.dateRepas', 'DESC')
            ->getQuery()
            ->getResult();

        $uniqueMeals = [];
        foreach ($history as $meal) {
            $signature = implode('|', [
                mb_strtolower(trim($meal->getNomRepas())),
                mb_strtolower(trim($meal->getTypeRepas())),
                (string) ($meal->getCalories() ?? 0),
                (string) ($meal->getProteines() ?? 0),
                (string) ($meal->getGlucides() ?? 0),
                (string) ($meal->getLipides() ?? 0),
                mb_strtolower(trim((string) ($meal->getCommentaire() ?? ''))),
            ]);

            if (isset($uniqueMeals[$signature])) {
                continue;
            }

            $uniqueMeals[$signature] = $meal;
        }

        $catalogMeals = array_values($uniqueMeals);
        usort($catalogMeals, static function (Repas $left, Repas $right): int {
            return strcasecmp($left->getNomRepas(), $right->getNomRepas());
        });

        return $catalogMeals;
    }

    private function hydrationSessionKey(User $user, ?RegimeAlimentaire $regime): string
    {
        $dateKey = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $regimeKey = $regime?->getId() ?? 0;

        return sprintf('diet_hydration_%d_%d_%s', (int) $user->getId(), (int) $regimeKey, $dateKey);
    }

    /**
     * @return array<string, int|string>|null
     */
    private function callSpoonacularImageAnalyze(UploadedFile $image, string $apiKey): ?array
    {
        $curl = curl_init('https://api.spoonacular.com/food/images/analyze?apiKey='.rawurlencode($apiKey));
        if ($curl === false) {
            return null;
        }

        $payload = [
            'image' => new \CURLFile($image->getPathname(), $this->safeUploadedFileMimeType($image, 'image/jpeg'), $image->getClientOriginalName()),
        ];

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($raw) || $raw === '' || $httpCode >= 400) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        $name = trim((string) ($data['category']['name'] ?? $data['annotations'][0]['tag'] ?? 'Repas analyse'));
        $calories = (int) round((float) ($data['nutrition']['calories']['value'] ?? $data['nutrition']['recipesUsed'] ?? 420));
        $protein = (int) round((float) ($data['nutrition']['protein']['value'] ?? 24));
        $carbs = (int) round((float) ($data['nutrition']['carbs']['value'] ?? 30));
        $fat = (int) round((float) ($data['nutrition']['fat']['value'] ?? 16));

        return [
            'food' => $name !== '' ? $name : 'Repas analyse',
            'calories' => $calories,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
            'confidence' => (int) round((float) (($data['category']['probability'] ?? 0.61) * 100)),
        ];
    }

    /**
     * @param int[] $conversationIds
     *
     * @return array<int, int>
     */
    private function fetchUnreadCounts(
        \Doctrine\DBAL\Connection $connection,
        array $conversationIds,
        int $userId
    ): array {
        if ($conversationIds === []) {
            return [];
        }

        $rows = $connection->executeQuery(
            'SELECT conversation_id, COUNT(id) AS unread_count
             FROM private_message
             WHERE conversation_id IN (?) AND receiver_id = ? AND is_read = 0
             GROUP BY conversation_id',
            [$conversationIds, $userId],
            [ArrayParameterType::INTEGER, ParameterType::INTEGER]
        )->fetchAllAssociative();

        $counts = array_fill_keys($conversationIds, 0);
        foreach ($rows as $row) {
            $counts[(int) ($row['conversation_id'] ?? 0)] = (int) ($row['unread_count'] ?? 0);
        }

        return $counts;
    }

    /**
     * @param int[] $conversationIds
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function fetchConversationLastMessages(
        \Doctrine\DBAL\Connection $connection,
        array $conversationIds
    ): array {
        if ($conversationIds === []) {
            return [[], []];
        }

        $rows = $connection->executeQuery(
            'SELECT pm.conversation_id, pm.content, pm.created_at
             FROM private_message pm
             INNER JOIN (
                SELECT conversation_id, MAX(id) AS last_id
                FROM private_message
                WHERE conversation_id IN (?)
                GROUP BY conversation_id
             ) last_messages ON last_messages.last_id = pm.id',
            [$conversationIds],
            [ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        $messages = [];
        $times = [];
        foreach ($rows as $row) {
            $conversationId = (int) ($row['conversation_id'] ?? 0);
            if ($conversationId <= 0) {
                continue;
            }

            $messages[$conversationId] = (string) ($row['content'] ?? '');
            $times[$conversationId] = !empty($row['created_at'])
                ? (new \DateTimeImmutable((string) $row['created_at']))->format('Y-m-d H:i')
                : '';
        }

        return [$messages, $times];
    }

    private function formatAgo(\DateTimeImmutable $time): string
    {
        $diff = (new \DateTimeImmutable())->getTimestamp() - $time->getTimestamp();
        if ($diff < 60) {
            return 'just now';
        }
        $mins = (int) floor($diff / 60);
        if ($mins < 60) {
            return $mins.' min ago';
        }
        $hours = (int) floor($mins / 60);
        if ($hours < 24) {
            return $hours.' h ago';
        }
        $days = (int) floor($hours / 24);
        return $days.' d ago';
    }

    /**
     * @param int[] $eventIds
     * @return array<int, array{participants: int}>
     */
    private function buildFrontEventStats(EntityManagerInterface $em, array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn (int $id): bool => $id > 0)));
        if ($eventIds === []) {
            return [];
        }

        $rows = $em->getRepository(Participation::class)
            ->createQueryBuilder('p')
            ->select('IDENTITY(p.event) AS eventId, COUNT(p.id) AS participants')
            ->where('IDENTITY(p.event) IN (:eventIds)')
            ->setParameter('eventIds', $eventIds)
            ->groupBy('p.event')
            ->getQuery()
            ->getArrayResult();
        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['eventId']] = ['participants' => (int) $row['participants']];
        }
        return $stats;
    }

    private function buildEventsLoyaltyIdentity(mixed $user, ?string $participantEmail): string
    {
        if ($user instanceof User) {
            $name = trim(($user->getFirstName() ?? '').' '.($user->getLastName() ?? ''));
            if ($name !== '' && $user->getEmail() !== null) {
                return $name.' | '.$user->getEmail();
            }
        }

        return $participantEmail !== null && $participantEmail !== ''
            ? $participantEmail
            : 'No connected member';
    }

    private function buildEventsLoyaltyTier(int $validReservations, int $vipThreshold): string
    {
        if ($validReservations >= $vipThreshold) {
            return 'VIP';
        }
        if ($validReservations >= max(2, $vipThreshold - 2)) {
            return 'Silver';
        }

        return 'Bronze';
    }

    private function buildEventsLoyaltyScore(int $validReservations, float $totalSpent): int
    {
        return ($validReservations * 10) + (int) round($totalSpent / 10);
    }

    /**
     * @return array{current: int, needed: int, nextTier: string, ratio: float, remaining: int}
     */
    private function buildEventsLoyaltyProgress(int $validReservations, int $vipThreshold, bool $canAccessVipEvents): array
    {
        $safeThreshold = max(1, $vipThreshold);
        $remaining = max(0, $safeThreshold - $validReservations);

        return [
            'current' => $validReservations,
            'needed' => $safeThreshold,
            'nextTier' => 'VIP',
            'ratio' => $canAccessVipEvents ? 1.0 : min(1.0, $validReservations / $safeThreshold),
            'remaining' => $remaining,
        ];
    }

    private function buildEventsLoyaltyMood(string $loyaltyTier, bool $canAccessVipEvents): string
    {
        if ($canAccessVipEvents) {
            return 'Backstage aura unlocked';
        }

        return match ($loyaltyTier) {
            'Silver' => 'Momentum is rising fast',
            'VIP' => 'VIP fast lane is fully active',
            default => 'Discovery mode is well underway',
        };
    }

    private function buildEventsLoyaltyBenefitsCopy(int $validReservations, int $vipThreshold, bool $canAccessVipEvents): string
    {
        if ($canAccessVipEvents) {
            return 'Priority pass unlocked: premium picks, VIP recommendations, and fast access to high-demand experiences.';
        }

        return max(0, $vipThreshold - $validReservations).' more valid booking(s) needed to unlock VIP status.';
    }

    /**
     * @param Event[] $events
     * @param array<int, int> $favoriteCounts
     * @param array<int, array{average: float, count: int}> $reviewStats
     * @param array<int, array<string, mixed>> $popularityScores
     * @param array<int, bool> $eventIsFullById
     * @param int[] $favoritedEventIds
     * @return array<int, array{event: Event, score: int, reason: string}>
     */
    private function buildFrontEventRecommendations(
        array $events,
        array $favoriteCounts,
        array $reviewStats,
        array $popularityScores,
        array $eventIsFullById,
        array $favoritedEventIds,
        bool $canAccessVipEvents
    ): array {
        $today = new \DateTimeImmutable('today');
        $favoritedLookup = array_fill_keys(array_map('intval', $favoritedEventIds), true);
        $recommendations = [];

        foreach ($events as $event) {
            $eventId = (int) ($event->getId() ?? 0);
            if ($eventId <= 0) {
                continue;
            }

            $avgRating = (float) ($reviewStats[$eventId]['average'] ?? 0.0);
            $reviewCount = (int) ($reviewStats[$eventId]['count'] ?? 0);
            $favoriteCount = (int) ($favoriteCounts[$eventId] ?? 0);
            $popularity = (int) (($popularityScores[$eventId]['score'] ?? 0) ?: 0);
            $isFull = (bool) ($eventIsFullById[$eventId] ?? false);
            $isFavorited = isset($favoritedLookup[$eventId]);
            $daysUntil = (int) $today->diff($event->getDateEvent())->format('%r%a');

            $score = 52;
            $score += min(18, (int) round($avgRating * 4));
            $score += min(12, $reviewCount * 2);
            $score += min(14, $favoriteCount * 2);
            $score += min(16, (int) round($popularity / 2));
            $score += $isFavorited ? 10 : 0;
            $score += $event->isPremium() && $canAccessVipEvents ? 8 : 0;
            $score -= $event->isPremium() && !$canAccessVipEvents ? 7 : 0;
            $score -= $isFull ? 8 : 0;
            $score += $daysUntil >= 0 && $daysUntil <= 14 ? 6 : 0;

            $reason = 'Balanced match for discovery mode.';
            $signal = mb_strtolower($event->getTitre().' '.$event->getTypeEvent().' '.$event->getDescription());
            if (str_contains($signal, 'yoga') || str_contains($signal, 'pilates') || str_contains($signal, 'wellness')) {
                $reason = 'Calm energy, recovery, and premium balance.';
            } elseif (str_contains($signal, 'marathon') || str_contains($signal, 'sport') || str_contains($signal, 'bootcamp') || str_contains($signal, 'cardio')) {
                $reason = 'Built for momentum, intensity, and high-engagement energy.';
            } elseif ($event->isPremium() && $canAccessVipEvents) {
                $reason = 'VIP profile detected: premium access boosts this pick.';
            } elseif ($isFull) {
                $reason = 'High demand event with strong community pull.';
            }

            $recommendations[] = [
                'event' => $event,
                'score' => max(50, min(99, $score)),
                'reason' => $reason,
            ];
        }

        usort($recommendations, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score'])
                ?: ($left['event']->getDateEvent()->getTimestamp() <=> $right['event']->getDateEvent()->getTimestamp());
        });

        return array_slice($recommendations, 0, 4);
    }

    /**
     * @return array{
     *   dnaValue: string,
     *   dnaHint: string,
     *   energyValue: string,
     *   energyHint: string,
     *   vipPathValue: string,
     *   vipPathHint: string
     * }
     */
    private function buildEventsHeroSignals(
        ?Event $focusEvent,
        ?int $matchScore,
        int $validReservations,
        int $vipThreshold,
        bool $canAccessVipEvents,
        int $favoriteCount
    ): array {
        $score = $matchScore ?? 84;
        $signal = $focusEvent instanceof Event
            ? mb_strtolower($focusEvent->getTitre().' '.$focusEvent->getTypeEvent().' '.$focusEvent->getDescription())
            : '';

        $energyValue = 'Explorer mode';
        $energyHint = 'A flexible match for discovery and broad exploration.';
        if ($signal !== '') {
            if (str_contains($signal, 'yoga') || str_contains($signal, 'pilates') || str_contains($signal, 'spa') || str_contains($signal, 'wellness')) {
                $energyValue = 'Recovery flow';
                $energyHint = 'Designed for calm focus, reset energy, and steady balance.';
            } elseif (str_contains($signal, 'run') || str_contains($signal, 'marathon') || str_contains($signal, 'boxing') || str_contains($signal, 'sport') || str_contains($signal, 'cardio') || str_contains($signal, 'bootcamp')) {
                $energyValue = 'Adrenaline rush';
                $energyHint = 'Built for momentum, intensity, and high-engagement participation.';
            } elseif ($focusEvent?->isPremium() && $canAccessVipEvents) {
                $energyValue = 'Elite track';
                $energyHint = 'Your VIP status unlocks a more exclusive event lane.';
            } elseif ($favoriteCount >= 3) {
                $energyValue = 'Collector mode';
                $energyHint = 'Your saved history shows a strong pattern of repeated interest.';
            }
        }

        if ($canAccessVipEvents) {
            $vipPathValue = 'VIP unlocked';
            $vipPathHint = 'Premium recommendations and fast-lane access are fully active.';
        } elseif ($validReservations > 0) {
            $remaining = max(0, $vipThreshold - $validReservations);
            $vipPathValue = $remaining.($remaining === 1 ? ' step to VIP' : ' steps to VIP');
            $vipPathHint = $validReservations.' valid booking(s) already captured in your passport.';
        } else {
            $vipPathValue = $vipThreshold.' steps to VIP';
            $vipPathHint = 'Connect a member account to activate loyalty memory and VIP tracking.';
        }

        return [
            'dnaValue' => max(50, min(99, $score)).'% match',
            'dnaHint' => 'Recommendation logic aligned this event with your current activity pattern.',
            'energyValue' => $energyValue,
            'energyHint' => $energyHint,
            'vipPathValue' => $vipPathValue,
            'vipPathHint' => $vipPathHint,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getFrontEventTypeImages(): array
    {
        return [
            'Yoga' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1400&q=80',
            'Nutrition' => 'https://images.unsplash.com/photo-1498837167922-ddd27525d352?auto=format&fit=crop&w=1400&q=80',
            'Sport' => 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?auto=format&fit=crop&w=1400&q=80',
            'Cardio' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1400&q=80',
        ];
    }

    /**
     * @param array<string, string> $typeImages
     */
    private function resolveEventImagePublicUrl(Event $event, array $typeImages): string
    {
        $fallback = $typeImages[$event->getTypeEvent()] ?? 'https://images.unsplash.com/photo-1476480862126-209bfaa8edc8?auto=format&fit=crop&w=1400&q=80';
        $imageValue = trim((string) ($event->getImageEvent() ?? ''));

        if ($imageValue === '') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $imageValue) === 1) {
            return $imageValue;
        }

        if (preg_match('#^(?:/?uploads/events/)?[a-zA-Z0-9._-]+\.(?:jpg|jpeg|png|webp|gif|avif)$#i', $imageValue) === 1) {
            $filename = basename($imageValue);
            return '/uploads/events/'.$filename;
        }

        $localPath = $this->normalizeEventLocalImagePath($imageValue);
        if ($localPath === null || !is_file($localPath)) {
            return $fallback;
        }

        $importedUrl = $this->importEventImageToPublic($localPath);
        if ($importedUrl !== null) {
            return $importedUrl;
        }

        $eventId = $event->getId();
        if ($eventId === null) {
            return $fallback;
        }

        return $this->generateUrl('events_image', ['id' => $eventId]);
    }

    private function normalizeEventLocalImagePath(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with(strtolower($raw), 'file:/')) {
            $parsedPath = parse_url($raw, PHP_URL_PATH);
            if (!is_string($parsedPath) || $parsedPath === '') {
                return null;
            }

            $normalized = urldecode(str_replace('\\', '/', $parsedPath));
            if (preg_match('#^/([A-Za-z]:/)#', $normalized) === 1) {
                $normalized = substr($normalized, 1);
            }

            return str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        }

        if (preg_match('#^[A-Za-z]:[\\\\/]#', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    private function importEventImageToPublic(string $localPath): ?string
    {
        if (!is_file($localPath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($localPath, PATHINFO_EXTENSION));
        if ($extension === '' || preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
            $extension = 'jpg';
        }

        $targetFilename = 'event-imported-'.sha1($localPath).'.'.$extension;
        $targetDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/events';
        $targetPath = $targetDirectory.'/'.$targetFilename;

        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            return null;
        }

        if (!is_file($targetPath) && !@copy($localPath, $targetPath)) {
            return null;
        }

        return is_file($targetPath) ? '/uploads/events/'.$targetFilename : null;
    }

    private function eventsTablesExist(EntityManagerInterface $em): bool
    {
        $tables = $this->existingTables($em, [
            'events',
            'participation',
            'reservation',
            'favorites',
            'reviews',
        ]);

        return $tables['events']
            && $tables['participation']
            && $tables['reservation']
            && $tables['favorites']
            && $tables['reviews'];
    }

    private function fitnessPlansTableExist(EntityManagerInterface $em): bool
    {
        $tables = $this->existingTables($em, ['fitness_plan']);
        return $tables['fitness_plan'];
    }

    /**
     * @param array<int, string> $tableNames
     * @return array<string, bool>
     */
    private function existingTables(EntityManagerInterface $em, array $tableNames): array
    {
        $result = [];
        $missing = [];

        foreach ($tableNames as $tableName) {
            if (array_key_exists($tableName, $this->tableExistsCache)) {
                $result[$tableName] = $this->tableExistsCache[$tableName];
            } else {
                $missing[] = $tableName;
            }
        }

        if ($missing === []) {
            return $result;
        }

        try {
            $rows = $em->getConnection()->fetchFirstColumn(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (:tables)",
                ['tables' => $missing],
                ['tables' => ArrayParameterType::STRING]
            );

            $found = array_fill_keys(array_map('strval', $rows), true);
            foreach ($missing as $tableName) {
                $exists = isset($found[$tableName]);
                $this->tableExistsCache[$tableName] = $exists;
                $result[$tableName] = $exists;
            }
        } catch (\Throwable) {
            foreach ($missing as $tableName) {
                $this->tableExistsCache[$tableName] = false;
                $result[$tableName] = false;
            }
        }

        return $result;
    }

    private function resolveProgramForPlan(string $programLabel, EntityManagerInterface $em): ?FitnessProgram
    {
        if ($programLabel === '') {
            return null;
        }

        $byTitle = $em->getRepository(FitnessProgram::class)->findOneBy(['title' => $programLabel]);
        if ($byTitle instanceof FitnessProgram) {
            return $byTitle;
        }

        return $em->getRepository(FitnessProgram::class)
            ->createQueryBuilder('p')
            ->where('p.category = :category')
            ->setParameter('category', $programLabel)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{
     *     id: int|null,
     *     title: string,
     *     program: string,
     *     place: string,
     *     estimatedMinutes: int,
     *     exercises: array<mixed>
     * }
     */
    private function serializeFitnessPlan(FitnessPlan $plan): array
    {
        return [
            'id' => $plan->getId(),
            'title' => $plan->getTitle(),
            'program' => $plan->getProgram()?->getTitle() ?? '',
            'place' => $plan->getPlace() ?? '',
            'estimatedMinutes' => $plan->getEstimatedMinutes() ?? 0,
            'exercises' => $plan->getExercisesData(),
        ];
    }

    private function handleFitnessPlannerPost(Request $request, EntityManagerInterface $em): void
    {
        $action = (string) $request->request->get('action');

        if ($action === 'create_program' || $action === 'update_program') {
            $program = $action === 'update_program'
                ? $em->getRepository(FitnessProgram::class)->find($request->request->getInt('id'))
                : new FitnessProgram();

            if (!$program) {
                $this->addFlash('error', 'Program not found.');
                return;
            }

            $title = trim((string) $request->request->get('title'));
            $category = trim((string) $request->request->get('category'));
            $level = trim((string) $request->request->get('level'));
            $sessionDuration = max(1, $request->request->getInt('session_duration', 45));
            $durationWeeks = max(1, $request->request->getInt('duration_weeks', 4));
            $sessionsPerWeek = max(1, $request->request->getInt('sessions_per_week', 3));

            if ($title === '' || $category === '' || $level === '') {
                $this->addFlash('error', 'Program title, category and level are required.');
                return;
            }

            $program->setTitle($title);
            $program->setCategory($category);
            $program->setLevel($level);
            $program->setDescription(trim((string) $request->request->get('description')) ?: null);
            $program->setSessionDuration($sessionDuration);
            $program->setDurationWeeks($durationWeeks);
            $program->setSessionsPerWeek($sessionsPerWeek);
            $program->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
            $program->setVideoUrl(trim((string) $request->request->get('video_url')) ?: null);
            $program->setIsPublic($request->request->getBoolean('is_public', true));

            foreach ($program->getExercises()->toArray() as $exercise) {
                $program->removeExercise($exercise);
            }
            $selectedExercises = $request->request->all('exercise_ids');
            foreach ($selectedExercises as $exerciseId) {
                $exercise = $em->getRepository(FitnessExercise::class)->find((int) $exerciseId);
                if ($exercise) {
                    $program->addExercise($exercise);
                }
            }

            $em->persist($program);
            $em->flush();
            $this->addFlash('success', $action === 'create_program' ? 'Program created.' : 'Program updated.');
            return;
        }

        if ($action === 'delete_program') {
            $program = $em->getRepository(FitnessProgram::class)->find($request->request->getInt('id'));
            if (!$program) {
                $this->addFlash('error', 'Program not found.');
                return;
            }
            $em->remove($program);
            $em->flush();
            $this->addFlash('success', 'Program deleted.');
            return;
        }

        if ($action === 'create_exercise' || $action === 'update_exercise') {
            $exercise = $action === 'update_exercise'
                ? $em->getRepository(FitnessExercise::class)->find($request->request->getInt('id'))
                : new FitnessExercise();

            if (!$exercise) {
                $this->addFlash('error', 'Exercise not found.');
                return;
            }

            $name = trim((string) $request->request->get('name'));
            $group = trim((string) $request->request->get('muscle_group'));
            $difficulty = trim((string) $request->request->get('difficulty'));

            if ($name === '' || $group === '' || $difficulty === '') {
                $this->addFlash('error', 'Exercise name, muscle group and difficulty are required.');
                return;
            }

            $exercise->setName($name);
            $exercise->setMuscleGroup($this->normalizeFitnessMuscleGroup($group));
            $exercise->setDifficulty($difficulty);
            $exercise->setDescription(trim((string) $request->request->get('description')) ?: null);
            $exercise->setPlace((string) $request->request->get('place', 'both'));
            $exercise->setSets($request->request->getInt('sets'));
            $exercise->setRepetitions($request->request->getInt('repetitions'));
            $exercise->setDuration($request->request->getInt('duration'));
            $exercise->setImageUrl(trim((string) $request->request->get('image_url')) ?: null);
            $exercise->setVideoUrl(trim((string) $request->request->get('video_url')) ?: null);

            $em->persist($exercise);
            $em->flush();
            $this->addFlash('success', $action === 'create_exercise' ? 'Exercise created.' : 'Exercise updated.');
            return;
        }

        if ($action === 'delete_exercise') {
            $exercise = $em->getRepository(FitnessExercise::class)->find($request->request->getInt('id'));
            if (!$exercise) {
                $this->addFlash('error', 'Exercise not found.');
                return;
            }
            $em->remove($exercise);
            $em->flush();
            $this->addFlash('success', 'Exercise deleted.');
        }
    }

    /**
     * @param list<FitnessExercise|array<string, mixed>> $exercises
     * @return list<array{label:string, items:list<FitnessExercise|array<string, mixed>>}>
     */
    private function buildExerciseGroups(array $exercises): array
    {
        $groupOrder = $this->fitnessMuscleGroupOrder();
        $grouped = [];

        foreach ($groupOrder as $group) {
            $grouped[$group] = [];
        }

        foreach ($exercises as $exercise) {
            $group = $this->normalizeFitnessMuscleGroup($this->readExerciseValue($exercise, 'muscleGroup'));
            if (!array_key_exists($group, $grouped)) {
                $grouped[$group] = [];
            }

            $grouped[$group][] = $exercise;
        }

        $result = [];
        foreach ($grouped as $label => $items) {
            if ($items === []) {
                continue;
            }

            $result[] = [
                'label' => $label,
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * @param list<FitnessExercise|array<string, mixed>> $exercises
     * @return array<string, array<string, list<array{name:string, image:string, video:string, difficulty:string, place:string}>>>
     */
    private function buildExerciseLibraryByGroup(array $exercises): array
    {
        $library = [];

        foreach ($this->fitnessMuscleGroupOrder() as $group) {
            $library[$group] = [
                'Beginner' => [],
                'Intermediate' => [],
                'Advanced' => [],
            ];
        }

        foreach ($exercises as $exercise) {
            $group = $this->normalizeFitnessMuscleGroup($this->readExerciseValue($exercise, 'muscleGroup'));
            $difficultyRaw = trim($this->readExerciseValue($exercise, 'difficulty'));
            $difficulty = $difficultyRaw !== '' ? $difficultyRaw : 'Beginner';

            if (!isset($library[$group])) {
                $library[$group] = [
                    'Beginner' => [],
                    'Intermediate' => [],
                    'Advanced' => [],
                ];
            }
            if (!isset($library[$group][$difficulty])) {
                $library[$group][$difficulty] = [];
            }

            $library[$group][$difficulty][] = [
                'name' => $this->readExerciseValue($exercise, 'name'),
                'image' => $this->readExerciseValue($exercise, 'imageUrl'),
                'video' => $this->readExerciseValue($exercise, 'videoUrl'),
                'difficulty' => $difficulty,
                'place' => $this->normalizeExercisePlace($this->readExerciseValue($exercise, 'place')),
            ];
        }

        return $library;
    }

    /**
     * @param FitnessExercise|array<string, mixed> $exercise
     */
    private function readExerciseValue(FitnessExercise|array $exercise, string $field): string
    {
        if ($exercise instanceof FitnessExercise) {
            return match ($field) {
                'name' => $exercise->getName(),
                'muscleGroup' => $exercise->getMuscleGroup(),
                'difficulty' => $exercise->getDifficulty(),
                'imageUrl' => (string) ($exercise->getImageUrl() ?? ''),
                'videoUrl' => (string) ($exercise->getVideoUrl() ?? ''),
                'place' => $exercise->getPlace(),
                default => '',
            };
        }

        $value = $exercise[$field] ?? '';
        return is_string($value) ? $value : (string) $value;
    }

    private function normalizeExercisePlace(?string $place): string
    {
        $normalized = strtolower(trim((string) $place));

        return match ($normalized) {
            'gym', 'salle' => 'gym',
            'home', 'maison' => 'home',
            default => 'both',
        };
    }

    /**
     * @return list<string>
     */
    private function fitnessMuscleGroupOrder(): array
    {
        return [
            'Poitrine',
            'Dos',
            'Jambes',
            'Fessier',
            'Epaules',
            'Biceps',
            'Triceps',
            'Avant-bras',
            'Abdos',
            'Entrainement du corps entier',
        ];
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

    #[Route('/diet-eating-planner/ai-chat', name: 'diet_ai_chat', methods: ['POST'])]
    public function dietAiChat(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->json([
                'ok' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        if (!$this->isCsrfTokenValid('diet_ai_chat', (string) $request->request->get('_token'))) {
            return $this->json([
                'ok' => false,
                'message' => 'Invalid security token.',
            ], 403);
        }

        $message = trim((string) $request->request->get('message'));
        if ($message === '') {
            return $this->json([
                'ok' => false,
                'message' => 'Message is required.',
            ], 400);
        }

        $regime = $em->getRepository(RegimeAlimentaire::class)->findOneBy(
            ['user' => $user],
            ['id' => 'DESC']
        );
        $todayMeals = $em->getRepository(Repas::class)->findBy(
            ['user' => $user],
            ['dateRepas' => 'DESC'],
            5
        );

        $goal = 'equilibre';
        $lower = mb_strtolower($message);
        if (str_contains($lower, 'perdre') || str_contains($lower, 'poids') || str_contains($lower, 'mincir')) {
            $goal = 'perte';
        } elseif (str_contains($lower, 'masse') || str_contains($lower, 'muscle') || str_contains($lower, 'prise')) {
            $goal = 'masse';
        } elseif (str_contains($lower, 'diab')) {
            $goal = 'diabete';
        }

        $caloriesTarget = $regime?->getCaloriesCibles() ? (int) $regime->getCaloriesCibles() : null;
        $mealIdeas = match ($goal) {
            'perte' => [
                'Petit-dejeuner: yaourt nature, flocons d’avoine, fruit.',
                'Dejeuner: poulet grille, legumes, riz complet.',
                'Diner: soupe legere, poisson, salade.',
            ],
            'masse' => [
                'Petit-dejeuner: omelette, pain complet, banane.',
                'Dejeuner: riz, viande maigre, avocat.',
                'Diner: pates completes, thon, legumes.',
            ],
            'diabete' => [
                'Prioriser les fibres, legumes verts et glucides a index glycemique modere.',
                'Fractionner les repas et eviter les sucres rapides.',
                'Associer toujours glucides + proteines + bons lipides.',
            ],
            default => [
                'Petit-dejeuner: produit laitier, fruit, cereales completes.',
                'Dejeuner: proteine maigre, legumes, feculent controle.',
                'Diner: repas leger et bien hydrate.',
            ],
        };

        $recentMeals = [];
        foreach ($todayMeals as $meal) {
            $recentMeals[] = trim((string) $meal->getNomRepas());
        }

        $reply = [];
        $reply[] = 'Objectif detecte: '.match ($goal) {
            'perte' => 'perte de poids',
            'masse' => 'prise de masse',
            'diabete' => 'equilibre pour diabete',
            default => 'equilibre general',
        }.'.';
        if ($caloriesTarget !== null && $caloriesTarget > 0) {
            $reply[] = 'Votre objectif calorique actuel est d’environ '.$caloriesTarget.' kcal/jour.';
        }
        if ($recentMeals !== []) {
            $reply[] = 'Repas recents detectes: '.implode(', ', array_slice($recentMeals, 0, 3)).'.';
        }
        $reply[] = 'Suggestions: '.implode(' ', $mealIdeas);
        $reply[] = 'Conseil Fitopia: buvez de l’eau, gardez 3 repas reguliers, puis ajustez selon votre progression.';

        return $this->json([
            'ok' => true,
            'reply' => implode(' ', $reply),
        ]);
    }

    #[Route('/explore/messages/start/{id}', name: 'explore_dm_start', methods: ['POST'])]
    public function startDm(
        User $recipient,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if ($recipient->getId() === $user->getId()) {
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $existing = $em->createQueryBuilder()
            ->select('c')
            ->from(DmConversation::class, 'c')
            ->where('(c.userA = :u1 AND c.userB = :u2) OR (c.userA = :u2 AND c.userB = :u1)')
            ->setParameter('u1', $user)
            ->setParameter('u2', $recipient)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing) {
            return $this->redirectToRoute('explore_forums_blogs', ['dm' => $existing->getId()]);
        }

        $conversation = new DmConversation();
        $conversation
            ->setUserA($user)
            ->setUserB($recipient)
            ->setLastMessageAt(new \DateTimeImmutable());

        $em->persist($conversation);
        $em->flush();

        return $this->redirectToRoute('explore_forums_blogs', ['dm' => $conversation->getId()]);
    }

    #[Route('/explore/{id}/interact', name: 'explore_interact', methods: ['POST'])]
    public function interact(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please login first.');
            return $this->redirectToRoute('app_login');
        }

        $type = (string) $request->request->get('type');
        if (!in_array($type, ['like', 'comment', 'repost', 'save'], true)) {
            $this->addFlash('error', 'Invalid interaction.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        if (!$this->isCsrfTokenValid('interact_'.$type.'_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $post = $em->getRepository(BlogPost::class)->find($id);
        if (!$post) {
            $this->addFlash('error', 'Post not found.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $connection = $em->getConnection();
        $postId = $post->getId();
        if ($postId === null) {
            $this->addFlash('error', 'Post not found.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        if ($type === 'comment') {
            $commentText = trim((string) $request->request->get('comment_text'));
            if ($commentText === '') {
                $this->addFlash('error', 'Comment cannot be empty.');
                return $this->redirectToRoute('explore_forums_blogs');
            }

            $connection->insert('forum_comments', [
                'user_id' => $user->getId(),
                'forum_id' => $postId,
                'content' => $commentText,
            ]);

            if ($post->getAuthor()?->getId() && $post->getAuthor()?->getId() !== $user->getId()) {
                $this->createForumNotification(
                    $connection,
                    (int) $post->getAuthor()->getId(),
                    (int) $user->getId(),
                    $postId,
                    'COMMENT',
                    'a commente votre post : "'.$post->getTitle().'"'
                );
            }

            $this->addFlash('success', 'Comment added.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $table = match ($type) {
            'like' => 'forum_likes',
            'repost' => 'forum_reposts',
            'save' => 'forum_saves',
            default => 'forum_likes',
        };
        $existing = $connection->fetchOne(
            "SELECT id FROM {$table} WHERE user_id = :user_id AND forum_id = :forum_id",
            [
                'user_id' => $user->getId(),
                'forum_id' => $postId,
            ]
        );

        if ($existing) {
            $connection->delete($table, ['id' => (int) $existing]);
            $this->addFlash('success', ucfirst($type).' removed.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $connection->insert($table, [
            'user_id' => $user->getId(),
            'forum_id' => $postId,
        ]);

        if (in_array($type, ['like', 'repost'], true) && $post->getAuthor()?->getId() && $post->getAuthor()?->getId() !== $user->getId()) {
            $verb = $type === 'like' ? 'aime votre post : "' : 'a republie votre post : "';
            $this->createForumNotification(
                $connection,
                (int) $post->getAuthor()->getId(),
                (int) $user->getId(),
                $postId,
                strtoupper($type),
                $verb.$post->getTitle().'"'
            );
        }

        $this->addFlash('success', ucfirst($type).' saved.');
        return $this->redirectToRoute('explore_forums_blogs');
    }

    #[Route('/explore/comments/{id}/update', name: 'explore_comment_update', methods: ['POST'])]
    public function updateExploreComment(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('comment_update_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $commentText = trim((string) $request->request->get('comment_text'));
        if ($commentText === '') {
            $this->addFlash('error', 'Comment cannot be empty.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $connection = $em->getConnection();
        $comment = $connection->fetchAssociative(
            'SELECT id, user_id, forum_id FROM forum_comments WHERE id = :id',
            ['id' => $id]
        );

        if (!is_array($comment)) {
            $this->addFlash('error', 'Comment not found.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        if ((int) ($comment['user_id'] ?? 0) !== (int) $user->getId()) {
            $this->addFlash('error', 'Only the author can edit this comment.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $connection->update('forum_comments', ['content' => $commentText], ['id' => $id]);
        $this->addFlash('success', 'Comment updated.');

        return $this->redirectToRoute('explore_forums_blogs');
    }

    #[Route('/explore/comments/{id}/delete', name: 'explore_comment_delete', methods: ['POST'])]
    public function deleteExploreComment(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('comment_delete_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $connection = $em->getConnection();
        $comment = $connection->fetchAssociative(
            'SELECT id, user_id FROM forum_comments WHERE id = :id',
            ['id' => $id]
        );

        if (!is_array($comment)) {
            $this->addFlash('error', 'Comment not found.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        if ((int) ($comment['user_id'] ?? 0) !== (int) $user->getId()) {
            $this->addFlash('error', 'Only the author can delete this comment.');
            return $this->redirectToRoute('explore_forums_blogs');
        }

        $connection->delete('forum_comments', ['id' => $id]);
        $this->addFlash('success', 'Comment deleted.');

        return $this->redirectToRoute('explore_forums_blogs');
    }

    #[Route('/explore/notifications/read', name: 'explore_notifications_read', methods: ['POST'])]
    public function markExploreNotificationsRead(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false], 401);
        }

        if (!$this->isCsrfTokenValid('explore_notifications_read', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false], 403);
        }

        $em->getConnection()->executeStatement(
            'UPDATE forum_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0',
            ['user_id' => $user->getId()]
        );

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/explore/{id}/summarize', name: 'explore_summarize', methods: ['POST'])]
    public function summarizeExplorePost(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        ExploreSummaryService $summaryService
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('explore_summarize_'.$id, (string) $request->request->get('_token'))) {
            return $this->json([
                'error' => 'Invalid security token.',
            ], 403);
        }

        $post = $em->getRepository(BlogPost::class)->find($id);
        if (!$post instanceof BlogPost) {
            return $this->json([
                'error' => 'Post not found.',
            ], 404);
        }

        $result = $summaryService->summarizeWithFallback(
            (string) $post->getTitle(),
            (string) $post->getContent(),
            (string) ($post->getExcerpt() ?? '')
        );

        return $this->json([
            'summary' => $result['summary'],
            'source' => $result['source'],
        ]);
    }

    /**
     * @param int[] $postIds
     *
     * @return array<int, array{
     *   likes: list<string>,
     *   reposts: list<string>,
     *   comments: list<array{id:int, user:string, email:string, text:string, date:string, userId:int}>
     * }>
     */
    private function buildForumInteractionMap(\Doctrine\DBAL\Connection $connection, array $postIds): array
    {
        $interactionMap = [];
        if ($postIds === []) {
            return $interactionMap;
        }

        $commentRows = $connection->executeQuery(
            'SELECT c.id, c.forum_id, c.user_id, c.content, c.created_at, u.email,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))), \'\'),
                             NULLIF(u.username, \'\'),
                             NULLIF(u.email, \'\'),
                             \'Anonymous\') AS author_name
             FROM forum_comments c
             LEFT JOIN fitopia_users u ON u.id = c.user_id
             WHERE c.forum_id IN (?) 
             ORDER BY c.created_at DESC',
            [$postIds],
            [ArrayParameterType::INTEGER]
        )->fetchAllAssociative();

        foreach ($commentRows as $row) {
            $forumId = (int) ($row['forum_id'] ?? 0);
            if ($forumId <= 0) {
                continue;
            }

            $interactionMap[$forumId] ??= [
                'likes' => [],
                'reposts' => [],
                'comments' => [],
            ];

            $date = $row['created_at'] ? (new \DateTimeImmutable((string) $row['created_at']))->format('Y-m-d H:i') : '';
            $interactionMap[$forumId]['comments'][] = [
                'id' => (int) ($row['id'] ?? 0),
                'userId' => (int) ($row['user_id'] ?? 0),
                'user' => (string) ($row['author_name'] ?? 'Anonymous'),
                'email' => (string) ($row['email'] ?? ''),
                'text' => (string) ($row['content'] ?? ''),
                'date' => $date,
            ];
        }

        foreach (['likes' => 'forum_likes', 'reposts' => 'forum_reposts'] as $bucket => $table) {
            $rows = $connection->executeQuery(
                "SELECT t.forum_id, u.email
                 FROM {$table} t
                 LEFT JOIN fitopia_users u ON u.id = t.user_id
                 WHERE t.forum_id IN (?)",
                [$postIds],
                [ArrayParameterType::INTEGER]
            )->fetchAllAssociative();

            foreach ($rows as $row) {
                $forumId = (int) ($row['forum_id'] ?? 0);
                if ($forumId <= 0) {
                    continue;
                }

                $interactionMap[$forumId] ??= [
                    'likes' => [],
                    'reposts' => [],
                    'comments' => [],
                ];

                $interactionMap[$forumId][$bucket][] = (string) ($row['email'] ?? 'Anonymous');
            }
        }

        return $interactionMap;
    }

    /**
     * @return int[]
     */
    private function fetchSavedForumIds(\Doctrine\DBAL\Connection $connection, int $userId, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return array_map(
            'intval',
            $connection->fetchFirstColumn(
                'SELECT forum_id FROM forum_saves WHERE user_id = :user_id AND forum_id IN (:post_ids)',
                ['user_id' => $userId, 'post_ids' => $postIds],
                ['user_id' => ParameterType::INTEGER, 'post_ids' => ArrayParameterType::INTEGER]
            )
        );
    }

    /**
     * @return list<array{id:int, type:string, content:string, isRead:bool, createdAt:string, fromName:string}>
     */
    private function fetchForumNotifications(\Doctrine\DBAL\Connection $connection, int $userId): array
    {
        $rows = $connection->fetchAllAssociative(
            'SELECT n.id, n.type, n.content, n.is_read, n.created_at,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, \'\'), \' \', COALESCE(u.last_name, \'\'))), \'\'),
                             NULLIF(u.username, \'\'),
                             NULLIF(u.email, \'\'),
                             \'Fitopia member\') AS from_name
             FROM forum_notifications n
             LEFT JOIN fitopia_users u ON u.id = n.from_user_id
             WHERE n.user_id = :user_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT 20',
            ['user_id' => $userId]
        );

        return array_map(static function (array $row): array {
            $createdAt = '';
            if (!empty($row['created_at'])) {
                $createdAt = (new \DateTimeImmutable((string) $row['created_at']))->format('d M H:i');
            }

            return [
                'id' => (int) ($row['id'] ?? 0),
                'type' => (string) ($row['type'] ?? 'INFO'),
                'content' => (string) ($row['content'] ?? ''),
                'isRead' => (bool) ($row['is_read'] ?? false),
                'createdAt' => $createdAt,
                'fromName' => (string) ($row['from_name'] ?? 'Fitopia member'),
            ];
        }, $rows);
    }

    private function createForumNotification(
        \Doctrine\DBAL\Connection $connection,
        int $userId,
        int $fromUserId,
        int $forumId,
        string $type,
        string $content
    ): void {
        $connection->insert('forum_notifications', [
            'user_id' => $userId,
            'from_user_id' => $fromUserId,
            'forum_id' => $forumId,
            'type' => strtoupper($type),
            'content' => $content,
            'is_read' => 0,
        ]);
    }

    private function ensureForumFeedSchema(\Doctrine\DBAL\Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS forum_saves (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                forum_id INT NOT NULL,
                UNIQUE KEY uk_forum_save (user_id, forum_id),
                KEY idx_forum_saves_forum (forum_id)
            )'
        );

        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS forum_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                from_user_id INT NOT NULL,
                forum_id INT DEFAULT NULL,
                type VARCHAR(50) NOT NULL,
                content TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    private function countActiveCommunityUsers(
        \Doctrine\DBAL\Connection $connection,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): int {
        $sql = <<<'SQL'
SELECT COUNT(*) FROM (
    SELECT user_id
    FROM forum_posts
    WHERE created_at >= :start AND created_at < :end
    UNION
    SELECT user_id
    FROM forum_comments
    WHERE created_at >= :start AND created_at < :end
) active_users
WHERE user_id IS NOT NULL
SQL;

        return (int) $connection->fetchOne($sql, [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    private function conversationIncludesUser(DmConversation $conversation, User $user): bool
    {
        return $conversation->getUserA()?->getId() === $user->getId()
            || $conversation->getUserB()?->getId() === $user->getId();
    }

    private function getOtherConversationUser(DmConversation $conversation, User $user): ?User
    {
        if ($conversation->getUserA()?->getId() === $user->getId()) {
            return $conversation->getUserB();
        }

        if ($conversation->getUserB()?->getId() === $user->getId()) {
            return $conversation->getUserA();
        }

        return null;
    }

    private function resolveForumImagePublicUrl(?string $imagePath): ?string
    {
        if ($imagePath === null || trim($imagePath) === '') {
            return null;
        }

        $raw = trim($imagePath);
        if (preg_match('#^https?://#i', $raw) === 1) {
            return $raw;
        }

        $uploadDir = $this->getParameter('app.blog_upload_dir');
        if (!is_string($uploadDir)) {
            return null;
        }

        if (preg_match('#^(?:/?uploads/blog/)?[a-zA-Z0-9._-]+\.(?:jpg|jpeg|png|webp|gif|avif)$#i', $raw) === 1) {
            $filename = basename(str_replace('\\', '/', $raw));
            return is_file($uploadDir . DIRECTORY_SEPARATOR . $filename) ? '/uploads/blog/' . $filename : null;
        }

        $localPath = $this->normalizeForumLocalImagePath($raw);
        if ($localPath === null || !is_file($localPath)) {
            return null;
        }

        return $this->importForumImageToPublic($localPath);
    }

    private function normalizeForumLocalImagePath(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with(strtolower($raw), 'file:/')) {
            $parsedPath = parse_url($raw, PHP_URL_PATH);
            if (!is_string($parsedPath) || $parsedPath === '') {
                return null;
            }

            $normalized = urldecode(str_replace('\\', '/', $parsedPath));
            if (preg_match('#^/([A-Za-z]:/)#', $normalized) === 1) {
                $normalized = substr($normalized, 1);
            }

            return str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        }

        if (preg_match('#^[A-Za-z]:[\\\\/]#', $raw) === 1) {
            return $raw;
        }

        return null;
    }

    private function importForumImageToPublic(string $localPath): ?string
    {
        if (!is_file($localPath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($localPath, PATHINFO_EXTENSION));
        if ($extension === '' || preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
            $extension = 'jpg';
        }

        $uploadDir = $this->getParameter('app.blog_upload_dir');
        if (!is_string($uploadDir)) {
            return null;
        }

        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            return null;
        }

        $targetFilename = 'blog-imported-' . sha1($localPath) . '.' . $extension;
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $targetFilename;

        if (!is_file($targetPath) && !@copy($localPath, $targetPath)) {
            return null;
        }

        return is_file($targetPath) ? '/uploads/blog/' . $targetFilename : null;
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

    private function safeUploadedFileMimeType(UploadedFile $file, string $fallback = 'application/octet-stream'): string
    {
        $clientMime = trim((string) $file->getClientMimeType());
        if ($clientMime !== '') {
            return $clientMime;
        }

        try {
            $mimeType = $file->getMimeType();
            if (is_string($mimeType) && $mimeType !== '') {
                return $mimeType;
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}







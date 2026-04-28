<?php
namespace App\Controller;

use App\Entity\AnalyticsEvent;
use App\Entity\Guardian;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AnalyticsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Endpoint public — reçoit les events du tracker JS
     * Pas de auth requise (les events arrivent aussi des visiteurs non connectés)
     */
    #[Route('/analytics/track', name: 'analytics_track', methods: ['POST'])]
    public function track(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['events'])) {
            return new JsonResponse(['ok' => false], 400);
        }

        $guardian  = $this->getUser();
        $ipHash    = hash('sha256', $request->getClientIp() . 'grooty_salt');
        $userAgent = substr($request->headers->get('User-Agent', ''), 0, 500);

        foreach ($data['events'] as $e) {
            if (empty($e['type']) || empty($e['page'])) continue;

            $event = new AnalyticsEvent();
            $event->setGuardian($guardian instanceof Guardian ? $guardian : null);
            $event->setSessionId(substr($data['sessionId'] ?? 'unknown', 0, 64));
            $event->setEventType(substr($e['type'], 0, 50));
            $event->setPage(substr($e['page'] ?? '/', 0, 500));
            $event->setTarget(isset($e['target']) ? substr($e['target'], 0, 500) : null);
            $event->setData($e['data'] ?? null);
            $event->setIpHash($ipHash);
            $event->setUserAgent($userAgent);

            $this->em->persist($event);
        }

        $this->em->flush();
        return new JsonResponse(['ok' => true]);
    }

    /**
     * Dashboard analytics — accès admin uniquement
     */
    #[Route('/admin/analytics', name: 'admin_analytics')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(Request $request): Response
    {
        if ($this->getUser()->getEmail() !== 'lesaichot.camille@gmail.com') {
            throw $this->createAccessDeniedException();
        }
        $days = $request->query->getInt('days', 7);
        $since = new \DateTime("-{$days} days");

        $conn = $this->em->getConnection();

        // Stats générales
        $stats = $conn->fetchAssociative("
            SELECT
                COUNT(*)                                           AS total_events,
                COUNT(DISTINCT session_id)                         AS sessions,
                COUNT(DISTINCT guardian_id)                        AS users,
                SUM(event_type = 'rage_click')                     AS rage_clicks,
                SUM(event_type = 'dead_click')                     AS dead_clicks,
                SUM(event_type = 'js_error')                       AS js_errors,
                SUM(event_type = 'page_view')                      AS page_views
            FROM analytics_event
            WHERE created_at >= ?
        ", [$since->format('Y-m-d H:i:s')]);

        // Pages les plus visitées
        $topPages = $conn->fetchAllAssociative("
            SELECT page, COUNT(*) AS views
            FROM analytics_event
            WHERE event_type = 'page_view' AND created_at >= ?
            GROUP BY page ORDER BY views DESC LIMIT 10
        ", [$since->format('Y-m-d H:i:s')]);

        // Rage clicks — zones problématiques
        $rageClicks = $conn->fetchAllAssociative("
            SELECT target, page, COUNT(*) AS count
            FROM analytics_event
            WHERE event_type = 'rage_click' AND created_at >= ?
            GROUP BY target, page ORDER BY count DESC LIMIT 10
        ", [$since->format('Y-m-d H:i:s')]);

        // Erreurs JS
        $jsErrors = $conn->fetchAllAssociative("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.message')) AS message,
                COUNT(*) AS count
            FROM analytics_event
            WHERE event_type = 'js_error' AND created_at >= ?
            GROUP BY message ORDER BY count DESC LIMIT 10
        ", [$since->format('Y-m-d H:i:s')]);

        // Funnel onboarding
        $funnel = $conn->fetchAllAssociative("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.step')) AS step,
                COUNT(*) AS count
            FROM analytics_event
            WHERE event_type = 'funnel' AND created_at >= ?
            GROUP BY step ORDER BY count DESC
        ", [$since->format('Y-m-d H:i:s')]);

        // Dead clicks — zones sans action
        $deadClicks = $conn->fetchAllAssociative("
            SELECT target, page, COUNT(*) AS count
            FROM analytics_event
            WHERE event_type = 'dead_click' AND created_at >= ?
              AND target IS NOT NULL
            GROUP BY target, page ORDER BY count DESC LIMIT 10
        ", [$since->format('Y-m-d H:i:s')]);

        // Actions clés
        $actions = $conn->fetchAllAssociative("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.action')) AS action,
                COUNT(*) AS count
            FROM analytics_event
            WHERE event_type = 'action' AND created_at >= ?
            GROUP BY action ORDER BY count DESC
        ", [$since->format('Y-m-d H:i:s')]);

        // Export JSON pour analyse IA
        $export = [
            'period'     => "{$days} derniers jours",
            'generated'  => date('Y-m-d H:i'),
            'stats'      => $stats,
            'topPages'   => $topPages,
            'rageClicks' => $rageClicks,
            'deadClicks' => $deadClicks,
            'jsErrors'   => $jsErrors,
            'funnel'     => $funnel,
            'actions'    => $actions,
        ];

        return $this->render('admin/analytics.html.twig', [
            'stats'      => $stats,
            'topPages'   => $topPages,
            'maxViews'   => !empty($topPages) ? max(array_column($topPages, 'views')) : 1,
            'rageClicks' => $rageClicks,
            'deadClicks' => $deadClicks,
            'jsErrors'   => $jsErrors,
            'funnel'     => $funnel,
            'actions'    => $actions,
            'days'       => $days,
            'exportJson' => json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Marquer une étape onboarding comme complète
     */
    #[Route('/onboarding/step/{step}', name: 'onboarding_step', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function onboardingStep(int $step): JsonResponse
    {
        $guardian = $this->getUser();
        if ($step > $guardian->getOnboardingStep()) {
            $guardian->setOnboardingStep($step);
            $this->em->flush();
        }
        return new JsonResponse(['step' => $guardian->getOnboardingStep()]);
    }
}

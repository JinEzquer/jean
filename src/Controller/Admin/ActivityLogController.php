<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    private ActivityLogRepository $activityLogRepository;

    public function __construct(ActivityLogRepository $activityLogRepository)
    {
        $this->activityLogRepository = $activityLogRepository;
    }

    #[Route('', name: 'admin_activity_logs', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $user = $request->query->get('user');
        $action = $request->query->get('action');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        $queryBuilder = $this->activityLogRepository->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC');

        // Apply filters
        if ($user) {
            $queryBuilder->andWhere('l.username LIKE :user')
                ->setParameter('user', "%$user%");
        }

        if ($action) {
            $queryBuilder->andWhere('l.action = :action')
                ->setParameter('action', $action);
        }

        if ($dateFrom) {
            $from = new \DateTimeImmutable($dateFrom);
            $queryBuilder->andWhere('l.createdAt >= :dateFrom')
                ->setParameter('dateFrom', $from);
        }

        if ($dateTo) {
            $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
            $queryBuilder->andWhere('l.createdAt <= :dateTo')
                ->setParameter('dateTo', $to);
        }

        $totalLogs = count($queryBuilder->getQuery()->getResult());
        $totalPages = ceil($totalLogs / $limit);

        $logs = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Get unique actions for filter dropdown
        $actions = $this->activityLogRepository->createQueryBuilder('l')
            ->select('DISTINCT l.action')
            ->orderBy('l.action', 'ASC')
            ->getQuery()
            ->getResult();

        $actionChoices = [];
        foreach ($actions as $actionItem) {
            $actionChoices[$actionItem['action']] = $actionItem['action'];
        }

        return $this->render('admin/activity_log/index.html.twig', [
            'logs' => $logs,
            'page_title' => 'Activity Logs',
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_logs' => $totalLogs,
            'filters' => [
                'user' => $user,
                'action' => $action,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'action_choices' => $actionChoices,
        ]);
    }

    #[Route('/{id}', name: 'admin_activity_log_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $log = $this->activityLogRepository->find($id);

        if (!$log) {
            throw $this->createNotFoundException('The activity log does not exist');
        }

        return $this->render('admin/activity_log/show.html.twig', [
            'log' => $log,
            'page_title' => 'Activity Log Details',
        ]);
    }
}

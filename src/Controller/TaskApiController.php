<?php
namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks')]
class TaskApiController extends AbstractController
{
    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    public function list(Request $r, TaskRepository $repo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $page  = max(1, (int) $r->query->get('page', 1));
        $limit = max(1, min(100, (int) $r->query->get('limit', 10)));

        $filters = [
            'status'  => $r->query->get('status'),
            'q'       => $r->query->get('q'),
            'overdue' => filter_var($r->query->get('overdue'), FILTER_VALIDATE_BOOLEAN),
            'sort'    => $r->query->get('sort', 'dueAt'),   // dueAt|createdAt
            'dir'     => strtoupper($r->query->get('dir', 'DESC')), // ASC|DESC
        ];

        $items = $repo->searchFor($this->getUser(), $filters, $page, $limit);

        $data = array_map(
            fn(Task $t) => [
                'id'       => $t->getId(),
                'title'    => $t->getTitle(),
                'status'   => $t->getStatus(),
                'priority' => $t->getPriority(),
                'dueAt'    => $t->getDueAt()?->format(DATE_ATOM),
                'createdAt' => $t->getCreatedAt()->format(DATE_ATOM),
                'updatedAt' => $t->getUpdatedAt()?->format(DATE_ATOM),
            ],
            $items
        );

        $total = $repo->countFor($this->getUser(), $filters);
        $pages = (int) ceil($total / $limit);

        return $this->json([
            'items'   => $data,
            'page'    => $page,
            'pages'   => $pages,
            'limit'   => $limit,
            'total'   => $total,
            'filters' => $filters,
        ]);
    }
}

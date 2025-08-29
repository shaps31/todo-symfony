<?php
namespace App\Controller;

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

        $filters = ['status'=>$r->query->get('status'), 'q'=>$r->query->get('q')];
        $page = (int) $r->query->get('page', 1);
        $limit = 10;

        $items = $repo->searchFor($this->getUser(), $filters, $page, $limit);

        // petit map => évite Serializer Groups pour l’instant
        $data = array_map(fn($t) => [
            'id'       => $t->getId(),
            'title'    => $t->getTitle(),
            'status'   => $t->getStatus(),
            'priority' => $t->getPriority(),
            'dueAt'    => $t->getDueAt()?->format(DATE_ATOM),
        ], $items);

        // total pour pagination
        $total = $repo->countFor($this->getUser(), $filters);
        $pages = (int) ceil($total / $limit);

        return $this->json(['items'=>$data, 'page'=>$page, 'pages'=>$pages, 'total'=>$total]);
    }
}

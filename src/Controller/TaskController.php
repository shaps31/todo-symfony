<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Event\TaskChangedEvent;
use App\Message\SendTaskCreatedEmailMessage;
use App\Message\SendTaskReminderEmailMessage;          // 👈 NEW
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;       // 👈 NEW

final class TaskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagAwareCacheInterface $cache,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly MessageBusInterface $bus,      // 👈 Messenger injecté
    ) {}

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('task_index');
    }

    #[Route('/tasks', name: 'task_index', methods: ['GET'])]
    public function index(Request $r, TaskRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $page   = max(1, (int) $r->query->get('page', 1));
        $limit  = 10;
        $user   = $this->getUser();

        $filters = [
            'status'  => $r->query->get('status'),
            'q'       => $r->query->get('q'),
            'overdue' => (bool) $r->query->get('overdue'),
            'sort'    => $r->query->get('sort', 'dueAt'),
            'dir'     => $r->query->get('dir', 'DESC'),
        ];

        $keyList  = sprintf('tasks:list:u%d:p%d:%s', $user->getId(), $page, md5(json_encode($filters)));
        $keyCount = sprintf('tasks:count:u%d:%s',    $user->getId(), md5(json_encode($filters)));
        $tagUser  = 'tasks_u'.$user->getId();

        $tasks = $this->cache->get($keyList, function (ItemInterface $item) use ($repo, $user, $filters, $page, $limit, $tagUser) {
            $item->expiresAfter(60);
            $item->tag([$tagUser]);
            return $repo->searchFor($user, $filters, $page, $limit);
        });

        $total = $this->cache->get($keyCount, function (ItemInterface $item) use ($repo, $user, $filters, $tagUser) {
            $item->expiresAfter(60);
            $item->tag([$tagUser]);
            return $repo->countFor($user, $filters);
        });

        $pages = (int) ceil($total / $limit);

        return $this->render('task/index.html.twig', [
            'tasks'   => $tasks,
            'filters' => $filters,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
        ]);
    }

    #[Route('/tasks/new', name: 'task_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setOwner($this->getUser());
            $this->em->persist($task);
            $this->em->flush();

            // 📨 Notification "créée"
            $this->bus->dispatch(new SendTaskCreatedEmailMessage($task->getId()));

            // ⏰ Rappel 24h avant l’échéance (ou immédiat si < 24h)
            $dueAt = $task->getDueAt();
            if ($dueAt instanceof \DateTimeInterface) {
                $now      = new \DateTimeImmutable();
                $targetTs = $dueAt->getTimestamp() - 24 * 3600;
                $delayMs  = max(0, ($targetTs - $now->getTimestamp()) * 1000);

                $this->bus->dispatch(
                    new SendTaskReminderEmailMessage($task->getId()),
                    [ new DelayStamp($delayMs) ]
                );
            }

            // 🛎️ Invalidation cache
            $this->dispatcher->dispatch(new TaskChangedEvent($this->getUser()->getId()));

            $this->addFlash('success', 'Tâche créée ✅');
            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'task_edit', methods: ['GET','POST'])]
    public function edit(Task $task, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($task->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            // ⏰ Reprogramme le rappel (au cas où la date a changé)
            $dueAt = $task->getDueAt();
            if ($dueAt instanceof \DateTimeInterface) {
                $now      = new \DateTimeImmutable();
                $targetTs = $dueAt->getTimestamp() - 24 * 3600;
                $delayMs  = max(0, ($targetTs - $now->getTimestamp()) * 1000);

                $this->bus->dispatch(
                    new SendTaskReminderEmailMessage($task->getId()),
                    [ new DelayStamp($delayMs) ]
                );
            }

            // 🛎️ Invalidation cache
            $this->dispatcher->dispatch(new TaskChangedEvent($this->getUser()->getId()));

            $this->addFlash('success', 'Tâche mise à jour ✅');
            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/edit.html.twig', ['form' => $form->createView(), 'task' => $task]);
    }



    #[Route('/tasks/export.csv', name: 'task_export', methods: ['GET'])]
    public function export(Request $r, TaskRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $filters = [
            'status'  => $r->query->get('status'),
            'q'       => $r->query->get('q'),
            'overdue' => (bool) $r->query->get('overdue'),
            'sort'    => $r->query->get('sort', 'dueAt'),
            'dir'     => $r->query->get('dir', 'DESC'),
        ];

        // on exporte “beaucoup” (ajuste si besoin)
        $items = $repo->searchFor($this->getUser(), $filters, 1, 5000);

        $response = new StreamedResponse(function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Title', 'Status', 'Priority', 'DueAt']);
            foreach ($items as $t) {
                fputcsv($out, [
                    $t->getTitle(),
                    $t->getStatus(),
                    $t->getPriority(),
                    $t->getDueAt()?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks.csv"');

        return $response;
    }


    #[Route('/tasks/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(Task $task, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($task->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), (string) $request->request->get('_token'))) {
            $this->em->remove($task);
            $this->em->flush();

            // 🛎️ Invalidation cache
            $this->dispatcher->dispatch(new TaskChangedEvent($this->getUser()->getId()));

            $this->addFlash('success', 'Tâche supprimée 🗑️');
        }
        return $this->redirectToRoute('task_index');
    }
}

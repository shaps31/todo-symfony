<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface; // ✅ bon namespace
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TaskController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {} // ✅ injection par constructeur

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('task_index');
    }

    #[Route('/tasks', name: 'task_index', methods: ['GET'])]
    public function index(Request $r, TaskRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $filters = [
            'status' => $r->query->get('status'),
            'q'      => $r->query->get('q'),
        ];
        $page = (int) $r->query->get('page', 1);

        // Assure-toi que TaskRepository::searchFor() attend bien App\Entity\User
        $tasks = $repo->searchFor($this->getUser(), $filters, $page);

        return $this->render('task/index.html.twig', compact('tasks', 'filters'));
    }

    #[Route('/tasks/new', name: 'task_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $task = new Task(); // valeurs par défaut dans l'entité: priority=low, status=todo, createdAt auto
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setOwner($this->getUser()); // 🔒 associer au propriétaire connecté
            $this->em->persist($task);
            $this->em->flush();

            $this->addFlash('success', 'Tâche créée ✅');
            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'task_edit')]
    public function edit(Task $task, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($task->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tâche mise à jour ✅');
            return $this->redirectToRoute('task_index');
        }

        return $this->render('task/edit.html.twig', ['form' => $form->createView(), 'task' => $task]);
    }

    #[Route('/tasks/{id}', name: 'task_delete', methods: ['POST'])]
    public function delete(Task $task, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($task->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), $request->request->get('_token'))) {
            $em->remove($task);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée 🗑️');
        }
        return $this->redirectToRoute('task_index');
    }
}

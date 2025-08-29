<?php
namespace App\MessageHandler;

use App\Message\SendTaskCreatedEmailMessage;
use App\Repository\TaskRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendTaskCreatedEmailMessageHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private MailerInterface $mailer,
    ) {}

    public function __invoke(SendTaskCreatedEmailMessage $msg): void
    {
        $task = $this->tasks->find($msg->taskId);
        if (!$task) return;

        $owner = $task->getOwner();

        $email = (new TemplatedEmail())
            ->from('noreply@todo.local')
            ->to($owner->getEmail())
            ->subject('Nouvelle tâche créée 📝')
            ->htmlTemplate('emails/task_created.html.twig')
            ->context([
                'title'    => $task->getTitle(),
                'priority' => $task->getPriority(),
                'dueAt'    => $task->getDueAt(),
            ]);

        $this->mailer->send($email);
    }
}

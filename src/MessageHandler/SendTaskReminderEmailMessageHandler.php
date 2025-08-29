<?php
namespace App\MessageHandler;

use App\Message\SendTaskReminderEmailMessage;
use App\Repository\TaskRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendTaskReminderEmailMessageHandler
{
    public function __construct(
        private TaskRepository $tasks,
        private MailerInterface $mailer,
    ) {}

    public function __invoke(SendTaskReminderEmailMessage $msg): void
    {
        $task = $this->tasks->find($msg->taskId);
        if (!$task) return;

        // on ne rappelle pas si déjà terminée
        if ($task->getStatus() === 'done') return;

        $owner = $task->getOwner();
        if (!$owner || !$owner->getEmail()) return;

        $email = (new TemplatedEmail())
            ->from('noreply@todo.local')
            ->to($owner->getEmail())
            ->subject('⏰ Rappel : "' . $task->getTitle() . '" pour ' . ($task->getDueAt()?->format('Y-m-d H:i') ?? 'bientôt'))
            ->htmlTemplate('emails/task_reminder.html.twig')
            ->context([
                'title' => $task->getTitle(),
                'priority' => $task->getPriority(),
                'dueAt' => $task->getDueAt(),
            ]);

        $this->mailer->send($email);
    }
}

<?php
namespace App\MessageHandler;

use App\Message\SendWelcomeEmailMessage;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendWelcomeEmailMessageHandler
{
    public function __construct(
        private UserRepository $users,
        private MailerInterface $mailer,
    ) {}

    public function __invoke(SendWelcomeEmailMessage $msg): void
    {
        $user = $this->users->find($msg->userId);
        if (!$user) return;

        $email = (new TemplatedEmail())
            ->from('noreply@todo.local')
            ->to($user->getEmail())
            ->subject('Bienvenue sur ToDo ✅')
            ->htmlTemplate('emails/welcome.html.twig')
            ->context(['userEmail' => $user->getEmail()]);


        $this->mailer->send($email);
    }
}

<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\TaskRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(name: 'app:daily-digest', description: 'Envoie un résumé des tâches à faire (aujourd’hui & en retard).')]
class DailyDigestCommand extends Command
{
    public function __construct(
        private UserRepository  $users,
        private TaskRepository  $tasks,
        private MailerInterface $mailer,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $todayEnd = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0);

        /** @var User $user */
        foreach ($this->users->findAll() as $user) {
            // tâches non "done" avec dueAt null (à faire) OU dueAt <= fin de journée
            $qb = $this->tasks->createQueryBuilder('t')
                ->andWhere('t.owner = :u')->setParameter('u', $user)
                ->andWhere('t.status <> :done')->setParameter('done', 'done')
                ->andWhere('(t.dueAt IS NULL OR t.dueAt <= :todayEnd)')->setParameter('todayEnd', $todayEnd)
                ->orderBy('t.dueAt', 'ASC');

            $list = $qb->getQuery()->getResult();
            if (!$list) continue;

            $email = (new TemplatedEmail())
                ->from('noreply@todo.local')
                ->to($user->getEmail())
                ->subject('🗞️ Digest quotidien de vos tâches')
                ->htmlTemplate('emails/digest.html.twig')
                ->context(['tasks' => $list]);

            $this->mailer->send($email);
        }

        $output->writeln('<info>Digest envoyé.</info>');
        return Command::SUCCESS;
    }
}

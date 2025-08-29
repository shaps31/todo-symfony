<?php
namespace App\DataFixtures;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    private function make(?string $relative = null, ?string $time = null): ?\DateTimeImmutable
    {
        if ($relative === null) return null;
        $dt = new \DateTimeImmutable($relative);              // ex: 'tomorrow', 'yesterday', '+2 days'
        if ($time) { [$h,$m] = explode(':', $time); $dt = $dt->setTime((int)$h, (int)$m); }
        return $dt;
    }

    public function load(ObjectManager $om): void
    {
        // Users
        $demo = (new User())->setEmail('demo@example.com');
        $demo->setPassword($this->hasher->hashPassword($demo, 'password'));
        $om->persist($demo);

        $shaps = (new User())->setEmail('shaps@test.com');
        $shaps->setPassword($this->hasher->hashPassword($shaps, 'password'));
        $om->persist($shaps);

        // Helper de création
        $mk = function(User $owner, string $title, string $status, string $prio, ?\DateTimeImmutable $due) use ($om) {
            $t = (new Task())
                ->setOwner($owner)
                ->setTitle($title)
                ->setDescription(ucfirst($title).' — tâche de démo')
                ->setStatus($status)
                ->setPriority($prio)
                ->setDueAt($due);
            $om->persist($t);
        };

        // Tasks demo@example.com
        $mk($demo, 'Préparer la démo',        'todo',  'high', $this->make('tomorrow', '09:00'));
        $mk($demo, 'Corriger bugs critiques', 'doing', 'high', $this->make('today', '18:00'));
        $mk($demo, 'Écrire les tests',        'todo',  'med',  null);
        $mk($demo, 'Mettre à jour la doc',    'done',  'low',  $this->make('yesterday', '14:00'));
        $mk($demo, 'Relire les PRs',          'todo',  'med',  $this->make('yesterday', '10:00')); // overdue

        // Tasks shaps@test.com
        $mk($shaps, 'Task A',                 'todo',  'low',  $this->make('+2 days', '10:00'));   // ✅ remplace "in 2 days 10:00"
        $mk($shaps, 'Task B',                 'doing', 'med',  $this->make('yesterday', '17:00')); // overdue
        $mk($shaps, 'Task C',                 'done',  'high', null);

        $om->flush();
    }
}

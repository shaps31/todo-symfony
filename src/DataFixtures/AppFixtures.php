<?php
namespace App\DataFixtures;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $om): void
    {
        $user = new User();
        $user->setEmail('demo@example.com');
        $user->setPassword($this->hasher->hashPassword($user, 'password'));
        $om->persist($user);

        for ($i=1; $i<=6; $i++) {
            $t = new Task();
            $t->setTitle("Tâche $i")
                ->setDescription("Demo $i")
                ->setPriority(['low','med','high'][array_rand(['low','med','high'])])
                ->setStatus(['todo','doing','done'][array_rand(['todo','doing','done'])])
                ->setOwner($user);
            $om->persist($t);
        }
        $om->flush();
    }
}

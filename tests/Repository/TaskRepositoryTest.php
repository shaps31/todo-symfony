<?php

namespace App\Tests\Repository;

use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaskRepositoryTest extends KernelTestCase
{
    public function testSearchForIsScopedToOwner(): void
    {
        self::bootKernel();

        $users = static::getContainer()->get(UserRepository::class);
        $tasks = static::getContainer()->get(TaskRepository::class);

        $demo = $users->findOneBy(['email' => 'demo@example.com']);
        $shaps = $users->findOneBy(['email' => 'shaps@test.com']);

        $filters = ['status' => null, 'q' => null, 'overdue' => false, 'sort' => 'dueAt', 'dir' => 'DESC'];

        $demoItems = $tasks->searchFor($demo, $filters, 1, 100);
        $shapsItems = $tasks->searchFor($shaps, $filters, 1, 100);

        $this->assertNotEmpty($demoItems);
        foreach ($demoItems as $t) {
            $this->assertSame($demo->getId(), $t->getOwner()->getId());
        }
        foreach ($shapsItems as $t) {
            $this->assertSame($shaps->getId(), $t->getOwner()->getId());
        }
    }
}

<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskControllerTest extends WebTestCase
{
    public function testRedirectIfNotLoggedIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tasks');
        self::assertResponseRedirects('/login');
    }

    public function testIndexShowsForLoggedUser(): void
    {
        $client = static::createClient();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneBy(['email' => 'demo@example.com']);

        $client->loginUser($user);
        $client->request('GET', '/tasks');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes tâches');
        self::assertSelectorExists('table');
    }
}

<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class MailTestController extends AbstractController
{
    #[Route('/_mail-test', name: 'mail_test')]
    public function __invoke(MailerInterface $mailer): Response
    {
        $mailer->send(
            (new Email())
                ->from('noreply@todo.local')
                ->to('dev@example.com')
                ->subject('Test Mailpit')
                ->text('OK')
        );

        return new Response('Mail envoyé — regarde http://127.0.0.1:8025');
    }
}

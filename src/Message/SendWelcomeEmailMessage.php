<?php
namespace App\Message;

final class SendWelcomeEmailMessage
{
    public function __construct(public int $userId) {}
}

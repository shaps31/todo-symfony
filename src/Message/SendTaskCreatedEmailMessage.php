<?php
namespace App\Message;

final class SendTaskCreatedEmailMessage
{
    public function __construct(public int $taskId) {}
}

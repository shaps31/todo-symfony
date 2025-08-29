<?php
namespace App\Message;

final class SendTaskReminderEmailMessage
{
    public function __construct(public int $taskId) {}
}

<?php

namespace App\Event;

final class TaskChangedEvent
{
    public function __construct(public int $userId)
    {
    }
}

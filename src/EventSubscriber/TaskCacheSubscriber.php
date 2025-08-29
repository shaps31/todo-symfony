<?php

namespace App\EventSubscriber;

use App\Event\TaskChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class TaskCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(private TagAwareCacheInterface $cache)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [TaskChangedEvent::class => 'onTaskChanged'];
    }

    public function onTaskChanged(TaskChangedEvent $event): void
    {
        // 💥 invalide tous les caches listés avec le tag de ce user
        $this->cache->invalidateTags(['tasks_u' . $event->userId]);
    }
}

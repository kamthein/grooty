<?php

namespace App\EventSubscriber;

use App\Entity\ChildGuardian;
use App\Entity\Event;
use App\Entity\Note;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsDoctrineListener(event: Events::postPersist)]
class NotificationSubscriber
{
    public function __construct(
        private NotificationService $notificationService,
        #[Autowire('%kernel.environment%')] private string $env
    ) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        if ($this->env === 'dev') return;

        $entity = $args->getObject();

        if ($entity instanceof Event && $entity->getCreatedBy()) {
            $this->notificationService->notifyNewEvent($entity, $entity->getCreatedBy());
            return;
        }

        if ($entity instanceof Note && $entity->getAuthor()) {
            $this->notificationService->notifyNewNote($entity, $entity->getAuthor());
            return;
        }

        if ($entity instanceof ChildGuardian && $entity->getInviteEmail() && $entity->getGuardian()) {
            $this->notificationService->sendInvitation($entity, $entity->getGuardian());
        }
    }
}

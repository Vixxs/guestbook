<?php

namespace App\EntityListener;

use App\Entity\Conference;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\String\Slugger\SluggerInterface;

class ConferenceEntityListener
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    public function prePersist(Conference $conference, LifecycleEventArgs $event): void
    {
        $this->computeSlug($conference);
    }

    public function preUpdate(Conference $conference, LifecycleEventArgs $event): void
    {
        $this->computeSlug($conference);
    }

    private function computeSlug(Conference $conference): void
    {
        $conference->computeSlug($this->slugger);
    }
}
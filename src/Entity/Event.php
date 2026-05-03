<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    const TYPE_VACANCES   = 'vacances';
    const TYPE_ACTIVITE   = 'activite';
    const TYPE_GARDE      = 'garde';
    const TYPE_MEDICAL    = 'medical';
    const TYPE_AUTRE      = 'autre';

    const RECURRENCE_NONE    = 'none';
    const RECURRENCE_DAILY   = 'daily';
    const RECURRENCE_WEEKLY  = 'weekly';
    const RECURRENCE_MONTHLY = 'monthly';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Child $child = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guardian $createdBy = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_AUTRE;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $startAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $allDay = false;

    #[ORM\Column(length: 50)]
    private string $recurrence = self::RECURRENCE_NONE;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTime $recurrenceEndAt = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $recurrenceGroupId = null;

    // Gardien responsable ce jour-là (ex: qui garde l'enfant)
    #[ORM\ManyToOne]
    private ?Guardian $responsibleGuardian = null;

    // Visibilité sélective : liste des guardian_ids qui peuvent voir cet événement
    // Si null ou vide → visible par tous les gardiens de l'enfant
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $visibleTo = null;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pendingSnapshot = null;

    // Image associée à l'événement (optionnel, réutilisable)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EventImage $image = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getChild(): ?Child { return $this->child; }
    public function setChild(?Child $child): static { $this->child = $child; return $this; }
    public function getCreatedBy(): ?Guardian { return $this->createdBy; }
    public function setCreatedBy(?Guardian $guardian): static { $this->createdBy = $guardian; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getStartAt(): ?\DateTimeInterface { return $this->startAt; }
    public function setStartAt(\DateTimeInterface $startAt): static { $this->startAt = $startAt; return $this; }
    public function getEndAt(): ?\DateTimeInterface { return $this->endAt; }
    public function setEndAt(?\DateTimeInterface $endAt): static { $this->endAt = $endAt; return $this; }
    public function isAllDay(): bool { return $this->allDay; }
    public function setAllDay(bool $allDay): static { $this->allDay = $allDay; return $this; }
    public function getRecurrence(): string { return $this->recurrence; }
    public function setRecurrence(string $recurrence): static { $this->recurrence = $recurrence; return $this; }
    public function getRecurrenceEndAt(): ?\DateTime { return $this->recurrenceEndAt; }
    public function setRecurrenceEndAt(?\DateTime $d): static { $this->recurrenceEndAt = $d; return $this; }
    public function getRecurrenceGroupId(): ?string { return $this->recurrenceGroupId; }
    public function setRecurrenceGroupId(?string $id): static { $this->recurrenceGroupId = $id; return $this; }
    public function getResponsibleGuardian(): ?Guardian { return $this->responsibleGuardian; }
    public function setResponsibleGuardian(?Guardian $guardian): static { $this->responsibleGuardian = $guardian; return $this; }
    public function getVisibleTo(): ?array { return $this->visibleTo; }
    public function setVisibleTo(?array $ids): static { $this->visibleTo = $ids; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function getPendingSnapshot(): ?array { return $this->pendingSnapshot; }
    public function setPendingSnapshot(?array $s): static { $this->pendingSnapshot = $s; return $this; }

    public function toSnapshot(): array
    {
        return [
            'title'       => $this->title,
            'type'        => $this->type,
            'startAt'     => $this->startAt?->format('d/m/Y H:i'),
            'endAt'       => $this->endAt?->format('d/m/Y H:i'),
            'allDay'      => $this->allDay,
            'description' => $this->description,
            'visibleTo'   => $this->visibleTo,
        ];
    }
    public function getImage(): ?EventImage { return $this->image; }
    public function setImage(?EventImage $image): static { $this->image = $image; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /**
     * Vérifie si un gardien peut voir cet événement
     */
    public function isVisibleBy(Guardian $guardian): bool
    {
        // Pas de restriction → visible par tous
        if (empty($this->visibleTo)) {
            return true;
        }
        return in_array($guardian->getId(), $this->visibleTo);
    }
}

<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EventHistory
{
    const ACTION_CREATED   = 'created';
    const ACTION_UPDATED   = 'updated';
    const ACTION_DELETED   = 'deleted';
    const ACTION_APPROVED  = 'approved';
    const ACTION_REJECTED  = 'rejected';
    const ACTION_NOTIFIED  = 'notified';
    const ACTION_PROPOSED  = 'proposed';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Event $event = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Guardian $guardian = null;

    #[ORM\Column(length: 30)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $snapshot = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $e): static { $this->event = $e; return $this; }
    public function getGuardian(): ?Guardian { return $this->guardian; }
    public function setGuardian(?Guardian $g): static { $this->guardian = $g; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $a): static { $this->action = $a; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $l): static { $this->label = $l; return $this; }
    public function getSnapshot(): ?array { return $this->snapshot; }
    public function setSnapshot(?array $s): static { $this->snapshot = $s; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

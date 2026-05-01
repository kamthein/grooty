<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EventChangeRequest
{
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';

    const STATUS_PENDING   = 'pending';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Guardian $requestedBy = null;

    #[ORM\Column(length: 20)]
    private string $action;

    #[ORM\Column(type: 'json')]
    private array $eventSnapshot = [];

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $resolvedAt = null;

    #[ORM\OneToMany(mappedBy: 'changeRequest', targetEntity: EventChangeApproval::class, cascade: ['persist', 'remove'])]
    private Collection $approvals;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->approvals = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(Event $e): static { $this->event = $e; return $this; }
    public function getRequestedBy(): ?Guardian { return $this->requestedBy; }
    public function setRequestedBy(?Guardian $g): static { $this->requestedBy = $g; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $a): static { $this->action = $a; return $this; }
    public function getEventSnapshot(): array { return $this->eventSnapshot; }
    public function setEventSnapshot(array $s): static { $this->eventSnapshot = $s; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getResolvedAt(): ?\DateTime { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTime $d): static { $this->resolvedAt = $d; return $this; }
    public function getApprovals(): Collection { return $this->approvals; }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }

    public function allApproved(): bool
    {
        foreach ($this->approvals as $a) {
            if ($a->getStatus() !== EventChangeApproval::STATUS_APPROVED) return false;
        }
        return !$this->approvals->isEmpty();
    }

    public function hasRejection(): bool
    {
        foreach ($this->approvals as $a) {
            if ($a->getStatus() === EventChangeApproval::STATUS_REJECTED) return true;
        }
        return false;
    }
}

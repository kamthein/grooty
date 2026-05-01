<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class EventChangeApproval
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'approvals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EventChangeRequest $changeRequest = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Guardian $guardian = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $respondedAt = null;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): ?int { return $this->id; }
    public function getChangeRequest(): ?EventChangeRequest { return $this->changeRequest; }
    public function setChangeRequest(EventChangeRequest $r): static { $this->changeRequest = $r; return $this; }
    public function getGuardian(): ?Guardian { return $this->guardian; }
    public function setGuardian(?Guardian $g): static { $this->guardian = $g; return $this; }
    public function getToken(): string { return $this->token; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getRespondedAt(): ?\DateTime { return $this->respondedAt; }
    public function setRespondedAt(?\DateTime $d): static { $this->respondedAt = $d; return $this; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
}

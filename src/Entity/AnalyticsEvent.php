<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(fields: ['eventType'], name: 'IDX_ae_type')]
#[ORM\Index(fields: ['createdAt'], name: 'IDX_ae_created')]
class AnalyticsEvent
{
    // Types d'événements
    const TYPE_PAGE_VIEW   = 'page_view';
    const TYPE_CLICK       = 'click';
    const TYPE_RAGE_CLICK  = 'rage_click';
    const TYPE_DEAD_CLICK  = 'dead_click';
    const TYPE_JS_ERROR    = 'js_error';
    const TYPE_ACTION      = 'action';      // création événement, note, invitation...
    const TYPE_FUNNEL      = 'funnel';      // étapes onboarding

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Guardian $guardian = null;

    #[ORM\Column(length: 64)]
    private string $sessionId;

    #[ORM\Column(length: 50)]
    private string $eventType;

    #[ORM\Column(length: 500)]
    private string $page;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $target = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipHash = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getGuardian(): ?Guardian { return $this->guardian; }
    public function setGuardian(?Guardian $g): static { $this->guardian = $g; return $this; }
    public function getSessionId(): string { return $this->sessionId; }
    public function setSessionId(string $s): static { $this->sessionId = $s; return $this; }
    public function getEventType(): string { return $this->eventType; }
    public function setEventType(string $t): static { $this->eventType = $t; return $this; }
    public function getPage(): string { return $this->page; }
    public function setPage(string $p): static { $this->page = $p; return $this; }
    public function getTarget(): ?string { return $this->target; }
    public function setTarget(?string $t): static { $this->target = $t; return $this; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $d): static { $this->data = $d; return $this; }
    public function getIpHash(): ?string { return $this->ipHash; }
    public function setIpHash(?string $h): static { $this->ipHash = $h; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): static { $this->userAgent = $ua; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

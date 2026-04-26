<?php

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteRepository::class)]
class Note
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Child $child = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guardian $author = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    // Visibilité sélective : mêmes règles que Event
    // null = visible par tous les gardiens de l'enfant
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $visibleTo = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'note', targetEntity: Attachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getChild(): ?Child { return $this->child; }
    public function setChild(?Child $child): static { $this->child = $child; return $this; }
    public function getAuthor(): ?Guardian { return $this->author; }
    public function setAuthor(?Guardian $guardian): static { $this->author = $guardian; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): static { $this->content = $content; return $this; }
    public function getVisibleTo(): ?array { return $this->visibleTo; }
    public function setVisibleTo(?array $ids): static { $this->visibleTo = $ids; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getAttachments(): Collection { return $this->attachments; }

    public function isVisibleBy(Guardian $guardian): bool
    {
        if (empty($this->visibleTo)) return true;
        return in_array($guardian->getId(), $this->visibleTo);
    }

    public function hasAttachments(): bool
    {
        return !$this->attachments->isEmpty();
    }
}

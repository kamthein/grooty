<?php

namespace App\Entity;

use App\Repository\ChildRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChildRepository::class)]
class Child
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(length: 50)]
    private string $theme = 'train';

    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $shareToken = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'child', targetEntity: ChildGuardian::class, cascade: ['persist', 'remove'])]
    private Collection $childGuardians;

    #[ORM\OneToMany(mappedBy: 'child', targetEntity: Event::class, cascade: ['persist', 'remove'])]
    private Collection $events;

    #[ORM\OneToMany(mappedBy: 'child', targetEntity: Note::class, cascade: ['persist', 'remove'])]
    private Collection $notes;

    public function __construct()
    {
        $this->childGuardians = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }
    public function getBirthDate(): ?\DateTimeInterface { return $this->birthDate; }
    public function setBirthDate(?\DateTimeInterface $birthDate): static { $this->birthDate = $birthDate; return $this; }
    public function getAvatarPath(): ?string { return $this->avatarPath; }
    public function setAvatarPath(?string $avatarPath): static { $this->avatarPath = $avatarPath; return $this; }
    public function getTheme(): string { return $this->theme; }
    public function setTheme(string $theme): static { $this->theme = $theme; return $this; }
    public function getShareToken(): ?string { return $this->shareToken; }
    public function setShareToken(?string $token): static { $this->shareToken = $token; return $this; }
    public function generateShareToken(): string
    {
        $this->shareToken = bin2hex(random_bytes(16));
        return $this->shareToken;
    }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getChildGuardians(): Collection { return $this->childGuardians; }
    public function getEvents(): Collection { return $this->events; }
    public function getNotes(): Collection { return $this->notes; }
}

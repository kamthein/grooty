<?php

namespace App\Entity;

use App\Repository\EventImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventImageRepository::class)]
class EventImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // L'image appartient à un enfant — réutilisable pour tous ses événements
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Child $child = null;

    // Qui a uploadé l'image
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Guardian $uploadedBy = null;

    // Chemin sur S3 / stockage local
    #[ORM\Column(length: 500)]
    private ?string $filePath = null;

    // Miniature 150x150
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $thumbnailPath = null;

    // Nom affiché dans la bibliothèque
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getChild(): ?Child { return $this->child; }
    public function setChild(?Child $child): static { $this->child = $child; return $this; }
    public function getUploadedBy(): ?Guardian { return $this->uploadedBy; }
    public function setUploadedBy(?Guardian $guardian): static { $this->uploadedBy = $guardian; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }
    public function getThumbnailPath(): ?string { return $this->thumbnailPath; }
    public function setThumbnailPath(?string $path): static { $this->thumbnailPath = $path; return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    // URL publique pour affichage direct (stockage local MAMP)
    public function getPublicPath(): string
    {
        return '/uploads/events/' . basename($this->filePath);
    }

    public function getPublicThumbnailPath(): string
    {
        $thumb = $this->thumbnailPath ?? $this->filePath;
        return '/uploads/events/thumbs/' . basename($thumb);
    }
}

<?php

namespace App\Entity;

use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
class Attachment
{
    const TYPE_PHOTO    = 'photo';
    const TYPE_DOCUMENT = 'document';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attachments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Note $note = null;

    #[ORM\Column(length: 50)]
    private ?string $type = self::TYPE_PHOTO;

    // Chemin sur S3 / Scaleway Object Storage
    #[ORM\Column(length: 500)]
    private ?string $filePath = null;

    // Miniature générée pour les photos
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $thumbnailPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $uploadedAt = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getNote(): ?Note { return $this->note; }
    public function setNote(?Note $note): static { $this->note = $note; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }
    public function getThumbnailPath(): ?string { return $this->thumbnailPath; }
    public function setThumbnailPath(?string $path): static { $this->thumbnailPath = $path; return $this; }
    public function getOriginalName(): ?string { return $this->originalName; }
    public function setOriginalName(?string $name): static { $this->originalName = $name; return $this; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $size): static { $this->fileSize = $size; return $this; }
    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    public function isPhoto(): bool { return $this->type === self::TYPE_PHOTO; }
}

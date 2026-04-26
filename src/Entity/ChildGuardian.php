<?php

namespace App\Entity;

use App\Repository\ChildGuardianRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChildGuardianRepository::class)]
class ChildGuardian
{
    // Roles disponibles pour un gardien
    const ROLE_PARENT    = 'parent';
    const ROLE_NOUNOU    = 'nounou';
    const ROLE_GRANDPARENT = 'grandparent';
    const ROLE_OTHER     = 'other';

    // Permissions
    const PERM_ADMIN     = 'admin';   // créateur, peut inviter/supprimer
    const PERM_EDIT      = 'edit';    // peut créer événements et notes
    const PERM_VIEW      = 'view';    // lecture seule

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'childGuardians')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Child $child = null;

    #[ORM\ManyToOne(inversedBy: 'childGuardians')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Guardian $guardian = null;

    // Email de la personne invitée (avant qu'elle crée son compte)
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $inviteEmail = null;

    #[ORM\Column(length: 50)]
    private ?string $role = self::ROLE_PARENT;

    #[ORM\Column(length: 50)]
    private ?string $permission = self::PERM_VIEW;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $joinedAt = null;

    // Token d'invitation avant que le gardien crée son compte
    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $inviteToken = null;

    #[ORM\Column(nullable: true)]
    private ?bool $inviteAccepted = false;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getChild(): ?Child { return $this->child; }
    public function setChild(?Child $child): static { $this->child = $child; return $this; }
    public function getGuardian(): ?Guardian { return $this->guardian; }
    public function setGuardian(?Guardian $guardian): static { $this->guardian = $guardian; return $this; }
    public function getInviteEmail(): ?string { return $this->inviteEmail; }
    public function setInviteEmail(?string $email): static { $this->inviteEmail = $email; return $this; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function getPermission(): ?string { return $this->permission; }
    public function setPermission(string $permission): static { $this->permission = $permission; return $this; }
    public function getJoinedAt(): ?\DateTimeImmutable { return $this->joinedAt; }
    public function getInviteToken(): ?string { return $this->inviteToken; }
    public function setInviteToken(?string $token): static { $this->inviteToken = $token; return $this; }
    public function isInviteAccepted(): ?bool { return $this->inviteAccepted; }
    public function setInviteAccepted(?bool $accepted): static { $this->inviteAccepted = $accepted; return $this; }
    public function isAdmin(): bool { return $this->permission === self::PERM_ADMIN; }
    public function canEdit(): bool { return in_array($this->permission, [self::PERM_ADMIN, self::PERM_EDIT]); }
}

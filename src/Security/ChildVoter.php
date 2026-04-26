<?php
namespace App\Security;

use App\Entity\Child;
use App\Entity\ChildGuardian;
use App\Entity\Guardian;
use App\Repository\ChildGuardianRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ChildVoter extends Voter
{
    const VIEW  = 'CHILD_VIEW';
    const EDIT  = 'CHILD_EDIT';
    const ADMIN = 'CHILD_ADMIN';

    public function __construct(private ChildGuardianRepository $cgRepo) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::ADMIN])
            && $subject instanceof Child;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $guardian = $token->getUser();
        if (!$guardian instanceof Guardian) return false;

        $cg = $this->cgRepo->findOneByChildAndGuardian($subject, $guardian);
        if (!$cg || !$cg->isInviteAccepted()) return false;

        return match ($attribute) {
            self::VIEW  => true, // tout gardien accepté peut voir
            self::EDIT  => $cg->canEdit(),
            self::ADMIN => $cg->isAdmin(),
            default     => false,
        };
    }
}

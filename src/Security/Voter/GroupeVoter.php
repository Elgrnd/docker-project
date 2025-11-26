<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class GroupeVoter extends Voter
{
    public const EDIT = 'GROUPE_EDIT';
    public const LEAVE = 'GROUPE_LEAVE';
    public const DELETE_MEMBER = 'GROUPE_DELETE_MEMBER';
    public const VIEW = 'GROUPE_VIEW';

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::EDIT, self::LEAVE, self::DELETE_MEMBER, self::VIEW])
            && $subject instanceof \App\Entity\Groupe;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if  (!$user instanceof UserInterface) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case self::EDIT:
                if ($user == null) {
                    return false;
                }
                else if ($this->security->isGranted('ROLE_ADMIN')) {
                    return true;
                }
                else if ($subject->getEtreChef() === $user) {
                    return true;
                }
                else {
                    return false;
                }

            case self::LEAVE:
                if ($subject->getEtreChef() === $user) {
                    return false;
                }
                else if ($subject->contientMembre($user)) {
                    return true;
                }
                else {
                    return false;
                }


            case self::VIEW:
                if ($this->security->isGranted('ROLE_ADMIN')) {
                    return true;
                }
                else if ($subject->contientMembre($user)) {
                    return true;
                }
                else {
                    return false;
                }
        }

        return false;
    }
}

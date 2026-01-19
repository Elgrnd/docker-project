<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class GroupeVoter extends Voter
{
    public const EDIT = 'GROUPE_EDIT';
    public const MODERATE = 'GROUPE_MODERATE';
    public const LEAVE = 'GROUPE_LEAVE';
    public const VIEW = 'GROUPE_VIEW';

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::EDIT, self::LEAVE, self::VIEW, self::MODERATE])
            && $subject instanceof \App\Entity\Groupe;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if  (!$user instanceof UserInterface) {
            return false;
        }

        $ug = $subject->getUtilisateurGroupePour($user);
        $role = '';
        if ($ug) {
            $role = $ug->getRole();
        }


        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case self::EDIT:
                if ($this->security->isGranted('ROLE_ADMIN')) {
                    return true;
                }
                else if ($subject->getEtreChef() === $user) {
                    return true;
                }
                else if ($role === 'GROUPE_ADMINISTRATEUR') {
                    return true;
                }
                else {
                    return false;
                }

            case self::MODERATE:
                if ($this->voteOnAttribute(self::EDIT, $subject, $token)) {
                    return true;
                }
                else if ($role === 'GROUPE_MODERATEUR') {
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

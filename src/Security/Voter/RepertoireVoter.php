<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class RepertoireVoter extends Voter
{
    public const OWNER = 'REP_EDIT';
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return $attribute == self::OWNER
            && $subject instanceof \App\Entity\Repertoire;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if  (!$user instanceof UserInterface) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        if ($attribute == self::OWNER) {
            if ($subject->getUtilisateurRepertoire() === $user) {
                return true;
            }

            $userGroups = $user->getUtilisateurGroupe();
            $repoGroup  = $subject->getGroupeRepertoire();

            if ($repoGroup !== null && $userGroups->contains($repoGroup)) {
                if ($this->security->isGranted('GROUPE_EDIT', $repoGroup)) {
                    return true;
                }
            }
            else {
                return false;
            }
        }

        return false;
    }
}

<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class RepertoireVoter extends Voter
{
    public const EDIT = 'REP_EDIT';
    public const UPLOAD = 'REP_UPLOAD';
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::UPLOAD])
            && $subject instanceof \App\Entity\Repertoire;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if  (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::EDIT:
                if ($subject->getUtilisateurRepertoire() === $user) {
                    return true;
                }

                $repoGroup  = $subject->getGroupeRepertoire();

                if ($repoGroup !== null) {
                    if ($this->security->isGranted('GROUPE_EDIT', $repoGroup)) {
                        return true;
                    }
                }
                else {
                    return false;
                }
                break;

            case self::UPLOAD:
                if ($subject->getUtilisateurRepertoire() === $user) {
                    return true;
                }

                $repoGroup  = $subject->getGroupeRepertoire();

                if ($repoGroup !== null) {
                    if ($this->security->isGranted('GROUPE_VIEW', $repoGroup)) {
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

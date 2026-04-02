<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class FileVoter extends Voter
{
    public const OWNER = 'FILE_OWNER';
    public const BIBLIO = 'FILE_BIBLIO';
    public const DOWNLOAD = 'FILE_DOWNLOAD';
    public const VIEW = 'FILE_VIEW';
    public const EDIT = 'FILE_EDIT';

    public function __construct(private Security $security) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::OWNER, self::BIBLIO, self::DOWNLOAD, self::VIEW, self::EDIT], true)
            && $subject instanceof \App\Entity\File;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if  (!$user instanceof UserInterface) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        switch ($attribute) {
            case self::OWNER:
                if ($subject->getUtilisateurFile() === $user) {
                    return true;
                }
                else {
                    return false;
                }

            case self::BIBLIO:
                if ($subject->getUtilisateurFile() === null) {
                    return true;
                }
                else {
                    return false;
                }
            case self::DOWNLOAD:
                if ($subject->getUtilisateurFile() === $user) {
                    return true;
                }

                if ($subject->getUtilisateurFile() === null) {
                    return true;
                }

                foreach ($subject->getGroupeParRepertoire() as $gfr) {
                    if ($gfr->getGroupe() && $gfr->getGroupe()->contientMembre($user)) {
                        return true;
                    }
                }

                foreach ($subject->getEtrePartages() as $partage) {
                    if ($partage->getUtilisateur() === $user) {
                        return true;
                    }
                }

                return false;

            case self::EDIT:
                if ($subject->getUtilisateurFile() === $user) {
                    return true;
                }

                foreach ($subject->getEtrePartages() as $partage) {
                    if (
                        $partage->getUtilisateur() === $user &&
                        $partage->getDroit() === 'edition'
                    ) {
                        return true;
                    }
                }

                return false;
        }

        return false;
    }
}

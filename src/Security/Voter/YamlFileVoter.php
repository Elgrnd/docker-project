<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class YamlFileVoter extends Voter
{
    public const OWNER = 'FILE_OWNER';
    public const BIBLIO = 'FILE_BIBLIO';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::OWNER, self::BIBLIO])
            && $subject instanceof \App\Entity\YamlFile;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if  (!$user instanceof UserInterface) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case self::OWNER:
                if ($subject->getUtilisateurYamlfile() === $user) {
                    return true;
                }
                else {
                    return false;
                }

            case self::BIBLIO:
                if ($subject->getUtilisateurYamlfile() === null) {
                    return true;
                }
                else {
                    return false;
                }
        }

        return false;
    }
}

<?php

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class GroupeYamlFileRepertoireVoter extends Voter
{
    public const EDIT = 'GROUPE_FILE_EDIT';
    public const DELETE = 'GROUPE_FILE_DELETE';
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof \App\Entity\GroupeYamlFileRepertoire;
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
                if ($this->security->isGranted('ROLE_ADMIN')) {
                    return true;
                }
                else if ($this->security->isGranted('FILE_OWNER', $subject->getYamlFile())) {
                    return true;
                }
                else if ($subject->getGroupe()->contientMembre($user) && $subject->getDroit() === 'edition') {
                    return true;
                }
                else {
                    return false;
                }

            case self::DELETE:
                if ($this->security->isGranted('ROLE_ADMIN')) {
                    return true;
                }
                else if ($subject->getGroupe()->getEtreChef() === $user) {
                    return true;
                }
                else if ($this->security->isGranted('FILE_OWNER', $subject->getYamlFile())) {
                    return true;
                }
                else if ($subject->getGroupe()->contientMembre($user) && $subject->getDroit() === 'edition') {
                    return true;
                }
                else {
                    return false;
                }
        }

        return false;
    }
}

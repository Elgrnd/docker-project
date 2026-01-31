<?php

namespace App\Security\Voter;

use App\Entity\VirtualMachine;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class VirtualMachineVoter extends Voter
{
    public const MANAGE = 'VM_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        return $attribute == self::MANAGE
            && $subject instanceof VirtualMachine;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case self::MANAGE:
                // logic to determine if the user can EDIT
                if($user->getVm() === $subject || in_array('ROLE_ADMIN', $user->getRoles())) {
                    return true;
                }
                // return true or false
            return false;
        }

        return false;
    }
}

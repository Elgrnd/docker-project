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
        return $attribute == self::MANAGE
            && $subject instanceof VirtualMachine;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case self::MANAGE:
                if ($user->getVm() === $subject) {
                    return true;
                }

                if (in_array('ROLE_ADMIN', $user->getRoles())) {
                    return true;
                }

                foreach ($user->getUtilisateurGroupe() as $groupe) {
                    if ($groupe->getVm() && $groupe->getVm() === $subject) {
                        return true;
                    }
                }

            return false;
        }

        return false;
    }
}

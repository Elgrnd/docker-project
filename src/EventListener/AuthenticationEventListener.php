<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationEventListener 
{
    
    public function __construct(RequestStack $requestStack){
        $this->requestStack = $requestStack;
    }
        
        #[AsEventListener]
        public function onLoginSuccessEvent(LoginSuccessEvent $event): void
        {
            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('success', 'Connexion réussie !');
        }
        
        #[AsEventListener]
        public function onLoginFailureEvent(LoginFailureEvent $event): void
        {
            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('error', 'Login et/ou mot de passe incorrect !');
        }

        #[AsEventListener]
        public function onLogoutEvent(LogoutEvent $event): void
        {
            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('success', 'Déconnexion réussie !');
        }
}
<?php

namespace App\EventSubscriber;

use App\Service\ProxmoxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthentificationSubscriber
{
    public function __construct(private RequestStack $requestStack,
                                private ProxmoxService $proxmoxService,
                                private EntityManagerInterface $entityManager)
    {
    }

    #[AsEventListener]
    public function loginSuccess(LoginSuccessEvent $event) {
        if($event->getAuthenticator()) {
            $request = $this->requestStack->getCurrentRequest();

            $createVm = $request?->request->get('create_vm');
            if($createVm) {
                if ($event->getUser()->getProxmoxVmid() === null) {
                    $event->getUser()->setVmStatus('creating');
                    $this->entityManager->flush();
                    $this->proxmoxService->cloneUserVmAsynchrone($event->getUser()->getLogin());
                }
            }

            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add("success", "Connexion réussie !");
        }
    }

    #[AsEventListener]
    public function loginFailure(LoginFailureEvent $event) {
        if($event->getAuthenticator()) {
            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add("error", "Login et/ou mot de passe incorrect !");
        }
    }

    #[AsEventListener]
    public function logout(LogoutEvent $event) {
        if($event->getResponse()) {
            if($event->getToken()->getUser()->getProxmoxVmid() !== null) {
                $this->proxmoxService->deleteVM($event->getToken()->getUser()->getProxmoxVmid());
                $event->getToken()->getUser()->setProxmoxVmid(null);
                $event->getToken()->getUser()->setVmStatus('none');
                $this->entityManager->flush();
            }

            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add("success", "Déconnexion réussie !");
        }
    }

}
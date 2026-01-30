<?php

namespace App\EventSubscriber;

use App\Message\DeleteVmMessage;
use App\Service\ProxmoxService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthentificationSubscriber
{
    public function __construct(private RequestStack $requestStack,
                                private ProxmoxService $proxmoxService,
                                private EntityManagerInterface $entityManager,
                                private MessageBusInterface $bus
    )
    {
    }

    #[AsEventListener]
    public function loginSuccess(LoginSuccessEvent $event) {
        if($event->getAuthenticator()) {
            $user = $event->getUser();

            if ($user->getVm() !== null) {
                $user->getVm()->setDeleteVmAt(null);
                $this->entityManager->flush();
            }
            $request = $this->requestStack->getCurrentRequest();

            $createVm = $request->request->get('create_vm');
            if($createVm) {
                if ($user->getVm() === null) {
                    $user->getVm()->setVmStatus('creating');
                    $this->entityManager->flush();
                    $this->proxmoxService->cloneUserVmAsynchrone($user->getLogin());
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
            $user = $event->getToken()->getUser();
            if ($user->getVm() !== null) {
                $user->getVm()->setDeleteVmAt(new DateTimeImmutable('+10 minutes'));
                $user->getVm()->setVmStatus('pending_delete');
                $this->entityManager->flush();

                $this->bus->dispatch(
                    new DeleteVmMessage($user->getId()),
                    [new DelayStamp(600000)]
                );
            }

            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('success',
                'Déconnexion réussie. Votre VM sera supprimée dans 10 minutes si vous ne vous reconnectez pas.'
            );
        }
    }


}
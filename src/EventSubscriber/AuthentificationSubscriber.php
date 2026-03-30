<?php

namespace App\EventSubscriber;

use App\Message\DeleteVmMessage;
use App\Repository\GroupeRepository;
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
                                private MessageBusInterface $bus,
                                private GroupeRepository $groupeRepo
    )
    {
    }

    #[AsEventListener]
    public function loginSuccess(LoginSuccessEvent $event): void
    {
        if($event->getAuthenticator()) {
            $user = $event->getUser();

            $promotionsEligibles = ['Ann1', 'Ann2', 'Ann3'];

            if (
                in_array($user->getPromotion(), $promotionsEligibles) &&
                $user->getClasse() === null
            ) {
                $session = $this->requestStack->getSession();
                $session->set('show_classe_popup', true);

                // Stocker les IDs et noms pour éviter de sérialiser des entités en session
                $classes = $this->groupeRepo->findBy(['isClass' => true]);
                $session->set('classes_disponibles', array_map(
                    fn($c) => ['id' => $c->getId(), 'nom' => $c->getNom()],
                    $classes
                ));
            }

            if ($user->getVm()->getVmId() !== null) {
                $user->getVm()->setDeleteVmAt(null);
                $user->getVm()->setVmStatus('ready');
                $this->entityManager->flush();
            }
            $request = $this->requestStack->getCurrentRequest();

            $createVm = $request->request->get('create_vm');
            if($createVm) {
                if ($user->getVm()->getVmId() === null) {
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
            if ($user->getVm()->getVmId() !== null) {
                $user->getVm()->setDeleteVmAt(new DateTimeImmutable('+30 seconds'));
                $user->getVm()->setVmStatus('pending_delete');
                $this->entityManager->flush();

                $this->bus->dispatch(
                    new DeleteVmMessage($user->getId()),
                    [new DelayStamp(30000)]
                );
            }

            $flashBag = $this->requestStack->getSession()->getFlashBag();
            $flashBag->add('success',
                'Déconnexion réussie. Votre VM sera supprimée dans 30 secondes si vous ne vous reconnectez pas.'
            );
        }
    }


}
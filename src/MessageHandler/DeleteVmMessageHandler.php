<?php

namespace App\MessageHandler;

use App\Message\DeleteVmMessage;
use App\Repository\UtilisateurRepository;
use App\Service\ProxmoxService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsMessageHandler]
class DeleteVmMessageHandler
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private ProxmoxService $proxmoxService,
        private EntityManagerInterface $entityManager
    ) {

    }

    /**
     * @throws TransportExceptionInterface
     */
    public function __invoke(DeleteVmMessage $message): void
    {
        $user = $this->utilisateurRepository->find($message->userId);

        if (!$user || $user->getVm()->getDeleteVmAt() === null) {
            return;
        }

        if ($user->getVm()->getDeleteVmAt() > new DateTimeImmutable()) {
            return;
        }

        $this->proxmoxService->deleteVM($user->getVm()->getVmId());

        $user->getVm()->setVmId(null);
        $user->getVm()->setVmStatus('none');
        $user->getVm()->setVmIp(null);
        $user->getVm()->setDeleteVmAt(null);

        $this->entityManager->flush();
    }

}
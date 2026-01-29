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

        if (!$user || $user->getDeleteVmAt() === null) {
            return;
        }

        if ($user->getDeleteVmAt() > new DateTimeImmutable()) {
            return;
        }

        $this->proxmoxService->deleteVM($user->getProxmoxVmid());

        $user->setProxmoxVmid(null);
        $user->setVmStatus('none');
        $user->setDeleteVmAt(null);

        $this->entityManager->flush();
    }

}
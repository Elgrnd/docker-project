<?php

namespace App\Command;

use App\Repository\UtilisateurRepository;
use App\Service\ProxmoxService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:delete-vm',
    description: 'Delete a virtual machine after delay',
)]
class DeleteVmCommand extends Command
{
    public function __construct(
        private ProxmoxService $proxmoxService,
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('userId', InputArgument::REQUIRED, 'Argument description')
        ;
    }

    /**
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userId = $input->getArgument('userId');

        $user = $this->utilisateurRepository->find($userId);

        if (!$user) {
            $io->error('Utilisateur introuvable');
            return Command::FAILURE;
        }
        if ($user->getProxmoxVmid() === null) {
            $io->note('Aucune VM à supprimer');
            return Command::SUCCESS;
        }
        if ($user->getDeleteVmAt() === null || $user->getDeleteVmAt() > new DateTimeImmutable()) {
            $io->note('Suppression annulée (utilisateur reconnecté)');
            return Command::SUCCESS;
        }
        $this->proxmoxService->deleteVM($user->getProxmoxVmid());
        $user->setProxmoxVmid(null);
        $user->setVmStatus('none');
        $user->setDeleteVmAt(null);
        $this->entityManager->flush();

        $io->success('VM supprimée avec succès');
        return Command::SUCCESS;
    }
}

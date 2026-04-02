<?php

namespace App\Command;

use App\Entity\VirtualMachine;
use App\Repository\UtilisateurRepository;
use App\Service\ProxmoxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:create-vm',
    description: "Create a virtual machine for the user login in parameter",
)]
class CreateVmCommand extends Command
{
    public function __construct(
        private ProxmoxService $proxmoxService,
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'Argument description')
        ;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $login = $input->getArgument('user');
        $user = $this->utilisateurRepository->findOneBy(["login" => $login]);
        if($user === null) {
            $io->error("User not found.");
            return Command::FAILURE;
        }
        $vmId = $this->proxmoxService->cloneVm(str_replace(' ', '', $user->getLogin()), $user->getVm()->getId());
        $user->getVm()->setVmId($vmId);
        $user->getVm()->setVmStatus('ready');
        $this->entityManager->flush();

        $io->success("The virtual machine has been created !");
        return Command::SUCCESS;
    }
}
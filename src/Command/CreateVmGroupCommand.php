<?php

namespace App\Command;

use App\Repository\GroupeRepository;
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
    name: 'app:create-vm-group',
    description: "Create a virtual machine for a group, with id of the group in parameter",
)]
class CreateVmGroupCommand extends Command
{
    public function __construct(
        private ProxmoxService $proxmoxService,
        private GroupeRepository $groupeRepository,
        private EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('idGroup', InputArgument::REQUIRED, 'Argument description')
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
        $idGroup = $input->getArgument('idGroup');
        $groupe = $this->groupeRepository->findOneBy(["id" => $idGroup]);
        if($groupe === null) {
            $io->error("Group not found.");
            return Command::FAILURE;
        }
        $vmId = $this->proxmoxService->cloneVm(str_replace(' ', '', $groupe->getNom()));
        $groupe->getVm()->setVmId($vmId);
        $groupe->getVm()->setVmStatus('ready');
        $this->entityManager->flush();

        $io->success("The group has been created !");
        return Command::SUCCESS;
    }
}
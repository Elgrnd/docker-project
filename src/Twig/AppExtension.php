<?php

namespace App\Twig;

use App\Repository\GroupeRepository;
use Symfony\Component\Form\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private GroupeRepository $groupeRepo) {}

    public function getGlobals(): array
    {
        return [
            'classes_disponibles' => $this->groupeRepo->findBy(['isClass' => true]),
        ];
    }
}
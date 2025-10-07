<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface YamlFileManagerInterface
{
    public function stockerYaml(UploadedFile $file, Utilisateur $user);
}
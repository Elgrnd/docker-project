<?php

namespace App\Form;

use App\Entity\EtrePartage;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurFileRepertoireRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtrePartageType extends AbstractType
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepo,
        private UtilisateurFileRepertoireRepository $ufrRepo,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $options['utilisateur'];
        $isAdmin = (bool) $options['is_admin'];

        $ufrs = $this->ufrRepo->findFilesForUserAdmin($utilisateur, $isAdmin);

        // idFile => label
        $fileChoices = [];
        foreach ($ufrs as $ufr) {
            $file = $ufr->getFile();
            $repertoire = $ufr->getRepertoire();
            $path = $repertoire?->getFullPath() ?? '';

            if ($isAdmin) {
                $owner = $file->getUtilisateurFile()?->getLogin() ?? 'inconnu';
                $label = sprintf('%s — %s / %s', $file->getNameFile(), $owner, $path);
            } else {
                $label = sprintf('%s — %s', $file->getNameFile(), $path);
            }

            $fileChoices[$label] = (string) $file->getId(); // ChoiceType: label => value
        }

        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choices' => $this->utilisateurRepo->findAllExcept($utilisateur),
                'choice_label' => 'login',
                'label' => 'Utilisateur à qui partager',
                'placeholder' => 'Sélectionner un utilisateur',
            ])
            ->add('fileId', ChoiceType::class, [
                'mapped' => false,                 // on ne mappe pas direct sur EtrePartage
                'choices' => $fileChoices,
                'placeholder' => 'Sélectionner un fichier',
                'label' => 'Fichier à partager',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtrePartage::class,
            'is_admin' => false,
            'utilisateur' => null,
        ]);
    }
}

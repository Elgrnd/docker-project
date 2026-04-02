<?php

namespace App\Form;

use App\Entity\EtrePartage;
use App\Entity\Utilisateur;
use App\Repository\EtrePartageRepository;
use App\Repository\UtilisateurFileRepertoireRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class EtrePartageType extends AbstractType
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepo,
        private UtilisateurFileRepertoireRepository $ufrRepo,
        private EtrePartageRepository $partageRepo,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $options['utilisateur'];
        $is_Admin = $options['is_Admin'];

        $fileChoices = [];
        $ufrs = $this->ufrRepo->findFilesForUserAdmin($utilisateur, $is_Admin);

        foreach ($ufrs as $ufr) {
            $file = $ufr->getFile();
            $repertoire = $ufr->getRepertoire();
            $owner = $ufr->getUtilisateur();

            $label = $file->getNameFile();

            if ($is_Admin && $owner) {
                $label .= ' (' . $owner->getLogin() . ')';
            }

            $label .= ' — ' . ($repertoire?->getFullPath() ?? '');

            $fileChoices[$label] = $file->getId();
        }

        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choices' => $this->utilisateurRepo->findAllExcept($utilisateur),
                'choice_label' => 'login',
                'placeholder' => 'Sélectionner un utilisateur',
            ])
            ->add('fichier', ChoiceType::class, [
                'mapped' => false,
                'choices' => $fileChoices,
                'placeholder' => 'Sélectionner un fichier',
            ])
            ->add('droit', ChoiceType::class, [
                'choices' => [
                    'Lecture' => 'lecture',
                    'Édition' => 'edition',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtrePartage::class,
            'is_Admin' => false,
            'utilisateur' => null,
        ]);
    }
}
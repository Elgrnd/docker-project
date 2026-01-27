<?php

namespace App\Form;

use App\Entity\Groupe;
use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartagerFileGroupeType extends AbstractType
{

    public function __construct(RepertoireRepository $repertoireRepository)
    {
        $this->repertoireRepository = $repertoireRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $groupe = $options['groupe'];

        $builder
            ->add('fileId', ChoiceType::class, [
                'label' => 'Sélectionnez un de vos fichiers',
                'placeholder' => '-- Choisir un fichier --',
                'choices' => $options['file_choices'],
                'attr' => [
                    'class' => 'form-select',
                ]
            ])
            ->add('repertoire', EntityType::class, [
                'class' => Repertoire::class,
                'choice_label' => function (Repertoire $repertoire) {
                    return $repertoire->getFullPath();
                },
                'mapped' => false,
                'label' => 'Répertoire de destination',
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ],
                'choices' => $this->repertoireRepository->recupererRepertoireGroupe($groupe->getId()),

                'help' => 'Choisissez le répertoire où sera enregistré votre fichier'
            ])
            ->add('droit', ChoiceType::class, [
                'label' => "Droit d'accès",
                'choices' => [
                    'Lecture seule' => 'lecture',
                    'Éditeur'       => 'edition',
                ],
                'attr' => [
                    'class' => 'form-select',
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'file_choices' => [],
            'groupe' => Groupe::class,
        ]);
    }
}

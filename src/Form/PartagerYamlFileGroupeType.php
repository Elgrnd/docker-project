<?php

namespace App\Form;

use App\Entity\Groupe;
use App\Entity\GroupeYamlFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\YamlFile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartagerYamlFileGroupeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('yamlId', ChoiceType::class, [
                'label' => 'Sélectionnez un de vos fichiers',
                'placeholder' => '-- Choisir un fichier --',
                'choices' => $options['yaml_choices'],
                'attr' => [
                    'class' => 'form-select',
                ]
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
            'yaml_choices' => [],
        ]);
    }
}

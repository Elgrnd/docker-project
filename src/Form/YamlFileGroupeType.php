<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class YamlFileGroupeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('yamlFile', FileType::class, [
                'label' => 'Fichier YAML à importer',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'text/plain',
                            'application/x-yaml',
                            'application/yaml',
                            'text/yaml',
                            'text/x-yaml',
                        ],
                        'mimeTypesMessage' => 'Veuillez importer un fichier YAML valide.',
                    ]),
                ],
                'attr' => [
                    'accept' => '.yaml,.yml',
                ],
            ])
            ->add('droit', ChoiceType::class, [
                'label' => 'Droits sur le fichier',
                'choices' => [
                    'Lecture seule' => 'lecture',
                    'Éditeur' => 'edition',
                ],
                'expanded' => true, // boutons radio
                'multiple' => false,
                'required' => true,
                'mapped' => false, // idem : on gère nous-mêmes
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
<?php

namespace App\Form;

use App\Entity\Groupe;
use App\Entity\GroupeYamlFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\YamlFile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class GroupeYamlFileRepertoireType extends AbstractType
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
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GroupeYamlFileRepertoire::class,
        ]);
    }
}

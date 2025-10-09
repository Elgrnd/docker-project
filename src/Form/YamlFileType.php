<?php

namespace App\Form;

use App\Entity\YamlFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class YamlFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nameFile', TextType::class, [
                'label' => 'Nom du fichier',
            ])
            ->add('yamlFile', FileType::class, [
                'label' => 'Fichier YAML',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'text/plain',
                            'application/x-yaml',
                            'application/yaml',
                        ],
                        'mimeTypesMessage' => 'Veuillez importer un fichier YAML valide',
                    ]),
                ],
                'attr' => [
                    'accept' => '.yaml,.yml'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => YamlFile::class,
        ]);
    }
}

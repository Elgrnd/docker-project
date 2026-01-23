<?php

namespace App\Form;

use App\Entity\YamlFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class YamlFileBiblioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
                            'text/yaml',
                            'text/x-yaml',
                        ],
                        'mimeTypesMessage' => 'Veuillez importer un fichier YAML valide',
                    ]),
                ],
                'attr' => [
                    'id' => 'yamlFileUpload',
                    'accept' => '.yaml,.yml',
                    'class' => 'form-control'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => YamlFile::class,
        ]);
    }
}

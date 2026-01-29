<?php

namespace App\Form;

use App\Entity\TextFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

class TextFileBiblioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new FileConstraint([
                        'maxSize' => '10M',
                        'maxSizeMessage' => 'Le fichier est trop volumineux (max {{ limit }}).',
                    ]),
                ],
                'help' => 'Tous les fichiers sont sélectionnables, mais la bibliothèque n’accepte que des fichiers texte.',
                'attr' => [
                    'id' => 'textFileUpload',
                    'class' => 'form-control'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TextFile::class,
        ]);
    }
}

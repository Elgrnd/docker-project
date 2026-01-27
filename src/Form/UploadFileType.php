<?php

namespace App\Form;

use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

class UploadFileType extends AbstractType
{
    private Security $security;
    private RepertoireRepository $repertoireRepository;

    public function __construct(Security $security, RepertoireRepository $repertoireRepository)
    {
        $this->security = $security;
        $this->repertoireRepository = $repertoireRepository;
    }

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
                'help' => 'Tous les fichiers sont sélectionnables (Dockerfile, .env, etc.). Les formats non autorisés seront refusés automatiquement.',
                'attr' => [
                    'id' => 'fileUpload',
                    'class' => 'form-control'
                ],
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
                'choices' => $this->repertoireRepository->recupererRepertoireUtilisateurActifs($this->security->getUser()),
                'help' => 'Choisissez le répertoire où sera enregistré votre fichier'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description du fichier',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ajoutez une description à votre fichier...'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}

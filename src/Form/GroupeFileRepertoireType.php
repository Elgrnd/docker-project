<?php

namespace App\Form;

use App\Entity\Groupe;
use App\Entity\GroupeFileRepertoire;
use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

class GroupeFileRepertoireType extends AbstractType
{
    private RepertoireRepository $repertoireRepository;

    public function __construct(RepertoireRepository $repertoireRepository)
    {
        $this->repertoireRepository = $repertoireRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $groupe = $options['groupe'];

        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier à importer',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new FileConstraint([
                        'maxSize' => '10M',
                        'maxSizeMessage' => 'Le fichier est trop volumineux (max {{ limit }}).',
                    ]),
                ],
                'help' => 'Tous les fichiers sont sélectionnables. Les formats non autorisés seront refusés automatiquement.',
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
                'choices' => $this->repertoireRepository->recupererRepertoireGroupeActifs($groupe),
                'help' => 'Choisissez le répertoire où sera enregistré votre fichier'
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
            'data_class' => GroupeFileRepertoire::class,
        ]);

        $resolver->setRequired('groupe');
        $resolver->setAllowedTypes('groupe', Groupe::class);
    }
}

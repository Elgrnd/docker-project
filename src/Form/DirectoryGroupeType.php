<?php

namespace App\Form;

use App\Entity\Groupe;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Repository\RepertoireRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class DirectoryGroupeType extends AbstractType
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
        $groupe = $options['groupe'];

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du répertoire',
                'attr' => [
                    'placeholder' => 'Mon nouveau répertoire',
                    'class' => 'form-control'
                ],
                'required' => true
            ])
            ->add('parent', EntityType::class, [
                'class' => Repertoire::class,
                'choice_label' => function (Repertoire $repertoire) {
                    return $repertoire->getFullPath();
                },
                'label' => 'Répertoire parent',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'choices' => $this->repertoireRepository->recupererRepertoireGroupe($groupe->getId()),
                // Sélectionner le répertoire racine par défaut

            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repertoire::class,
            'groupe' => Groupe::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\EtrePartage;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartagerFichierType extends AbstractType
{
    public function __construct(private UtilisateurRepository $utilisateurRepo) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $utilisateur = $options['utilisateur'];

        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choices' => $this->utilisateurRepo->findAllExcept($utilisateur),
                'choice_label' => 'login',
                'label' => 'Partager avec',
                'placeholder' => 'Utilisateur',
            ])
            ->add('droit', ChoiceType::class, [
                'choices' => [
                    'Lecture' => 'lecture',
                    'Édition' => 'edition',
                ],
                'label' => 'Droit',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtrePartage::class,
            'utilisateur' => null,
        ]);
    }
}
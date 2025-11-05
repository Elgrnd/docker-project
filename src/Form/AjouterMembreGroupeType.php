<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AjouterMembreGroupeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => 'login', // Affiche le login dans la liste
                'label' => 'Choisir un utilisateur à ajouter',
                'placeholder' => 'Sélectionnez un utilisateur',
                'required' => true,
                'attr' => [
                    'class' => 'form-select', // pour un peu de style bootstrap/tailwind
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // Pas d'entité directement liée
        ]);
    }
}

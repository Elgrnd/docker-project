<?php

namespace App\Form;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AjouterMembreGroupeType extends AbstractType
{
    private UtilisateurRepository $utilisateurRepository;
    public function __construct(UtilisateurRepository $utilisateurRepository)
    {
        $this->utilisateurRepository = $utilisateurRepository;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $groupe = $options['groupe'];

        $builder->add('utilisateur', EntityType::class, [
            'class' => Utilisateur::class,
            'choice_label' => 'login',
            'choices' => $this->utilisateurRepository->findNonMembresDuGroupe($groupe),
            'label' => 'Choisir un utilisateur à ajouter',
            'placeholder' => 'Sélectionnez un utilisateur',
            'required' => true,
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez sélectionner un utilisateur',
                ]),
            ],
            'attr' => [
                'class' => 'form-select'
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // Pas d'entité directement liée
        ]);
        $resolver->setRequired('groupe');
        $resolver->setAllowedTypes('groupe', Groupe::class);
    }
}

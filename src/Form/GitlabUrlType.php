<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GitlabUrlType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gitlabUrl', UrlType::class, [
                'label' => 'URL GitLab du projet',
                'required' => true,
                'attr' => [
                    'placeholder' => 'https://gitlab.../groupe/projet/-/tree/main',
                ]
            ])
            ->add('gitlabToken', PasswordType::class, [
                'label' => 'Token GitLab (PRIVATE-TOKEN) — requis pour dépôt privé',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'glpat-xxxxxxxxxxxx',
                    'autocomplete' => 'new-password',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}

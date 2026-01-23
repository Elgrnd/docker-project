<?php

namespace App\Form;

use App\Entity\Utilisateur;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('login', TextType::class, [
                'label' => 'Login',
                'attr' => [
                    'class' => 'form-control',
                    'minlength' => 4,
                    'maxlength' => 20,
                    'autocomplete' => 'username',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 4, max: 20),
                ],
            ])
            ->add('adresseMail',  EmailType::class, [
                'label' => 'Adresse mail',
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control',
                    'minlength' => 8,
                    'maxlength' => 30,
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(
                        min: 8,
                        max: 30,
                        minMessage: 'Votre mot de passe doit faire au moins 8 caractères',
                        maxMessage: 'Votre mot de passe ne peut pas dépasser 30 caractères'
                    ),
                    new Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        message: 'Il faut au moins une minuscule, une majuscule et un chiffre'
                    ),
                ],
            ])
            ->add('inscription', SubmitType::class, [
                'label' => "S'inscrire",
                'attr' => [
                    'class' => 'btn btn-primary w-100 fw-semibold',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateur::class,
        ]);
    }
}

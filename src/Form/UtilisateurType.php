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
                'attr' => [
                    'minlength' => 4,
                    'maxlength' => 20,
                ]
            ])
            ->add('adresseMail',  EmailType::class, [
                'label' => 'Adresse mail',
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'minlength' => 8,
                    'maxlength' => 30,
                    'pattern' => '^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,30}$'
                ],
                'constraints' => [
                    new NotBlank(),
                    new NotNull(),
                    new Length(min: 8,
                        max: 30,
                        minMessage: "Votre mot de passe doit faire plus de 8 caractères",
                        maxMessage: "Votre mot de passe doit faire plus de 30 caractères"),
                    new Regex("#^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,30}$#",
                        message: "Il faut au moins une minuscule, une majuscule et un chiffre dans votre mot de passe !")
                ],
                'label' => "Mot de passe"
            ])
            ->add('inscription', SubmitType::class, [
                'label' => "S'inscrire"
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

<?php

namespace App\Form;

use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class DirectoryType extends AbstractType
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $this->security->getUser();

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
                'query_builder' => function ($repository) use ($user) {
                    return $repository->createQueryBuilder('r')
                        ->where('r.utilisateur_id = :user')
                        ->setParameter('user', $user)
                        ->orderBy('r.name', 'ASC');
                },
                // Sélectionner le répertoire racine par défaut
                'preferred_choices' => function ($repertoire, $key, $value) {
                    return $repertoire->isRoot();
                }
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repertoire::class,
        ]);
    }
}
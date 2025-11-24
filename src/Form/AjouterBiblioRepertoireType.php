<?php

namespace App\Form;

use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bundle\SecurityBundle\Security;

class AjouterBiblioRepertoireType extends AbstractType
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
        $user = $this->security->getUser();

        $builder
            ->add('repertoire', EntityType::class, [
                'class' => Repertoire::class,
                'choice_label' => function (Repertoire $repertoire) {
                    return $repertoire->getFullPath();
                },
                'label' => 'Répertoire de destination',
                'required' => true,
                'placeholder' => 'Sélectionnez un répertoire',
                'attr' => [
                    'class' => 'form-select'
                ],
                'choices' => $this->repertoireRepository->recupererRepertoireUtilisateur($user),
                'help' => 'Choisissez le répertoire où sera enregistré votre fichier'
            ]);
        // Pas de champ submit ici
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

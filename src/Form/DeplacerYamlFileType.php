<?php

namespace App\Form;

use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bundle\SecurityBundle\Security;

class DeplacerYamlFileType extends AbstractType
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
                'choice_label' => fn($r) => $r->getFullPath(),
                'choices' => $this->repertoireRepository->recupererRepertoireUtilisateurActifs($user),
                'label' => 'Répertoire de destination',
                'placeholder' => 'Sélectionnez un répertoire',
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

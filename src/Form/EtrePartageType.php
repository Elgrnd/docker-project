<?php

namespace App\Form;

use App\Entity\EtrePartage;
use App\Entity\Utilisateur;
use App\Entity\YamlFile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtrePartageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => 'login',
                'label' => 'Utilisateur à qui partager',
                'placeholder' => 'Sélectionner un utilisateur',
            ])
            ->add('yamlFile', EntityType::class, [
                'class' => YamlFile::class,
                'choice_label' => 'nameFile',
                'label' => 'Fichier YAML à partager',
                'placeholder' => 'Sélectionner un fichier YAML',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtrePartage::class,
        ]);
    }
}

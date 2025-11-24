<?php

namespace App\Form;

use App\Entity\EtrePartage;
use App\Entity\Utilisateur;
use App\Entity\YamlFile;
use App\Repository\UtilisateurRepository;
use App\Repository\YamlFileRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtrePartageType extends AbstractType
{
    private UtilisateurRepository $utilisateurRepo;
    private YamlFileRepository $yamlRepo;

    public function __construct(UtilisateurRepository $utilisateurRepo, YamlFileRepository $yamlRepo)
    {
        $this->utilisateurRepo = $utilisateurRepo;
        $this->yamlRepo = $yamlRepo;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['is_admin'];
        $utilisateur = $options['utilisateur'];

        $builder
            ->add('utilisateur', EntityType::class, [
                'class' => Utilisateur::class,
                'choices' => $this->utilisateurRepo->findAllExcept($utilisateur),
                'choice_label' => 'login',
                'label' => 'Utilisateur à qui partager',
                'placeholder' => 'Sélectionner un utilisateur',
            ])
            ->add('yamlFile', EntityType::class, [
                'class' => YamlFile::class,
                'choices' => $this->yamlRepo->findForUser($utilisateur, $isAdmin),
                'choice_label' => $isAdmin
                    ? fn (YamlFile $file) => sprintf('%s (par %s)', $file->getNameFile(), $file->getUtilisateurYamlfile()->getLogin())
                    : 'nameFile',
                'label' => 'Fichier YAML à partager',
                'placeholder' => 'Sélectionner un fichier YAML',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtrePartage::class,
            'is_admin' => false,
            'utilisateur' => null,
        ]);
    }
}

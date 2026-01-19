<?php

namespace App\Form;

use App\Entity\EtrePartage;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Repository\UtilisateurRepository;
use App\Repository\UtilisateurYamlFileRepertoireRepository;
use App\Repository\YamlFileRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtrePartageType extends AbstractType
{
    private UtilisateurRepository $utilisateurRepo;
    private YamlFileRepository $yamlRepo;
    private UtilisateurYamlFileRepertoireRepository $uyr;

    public function __construct(UtilisateurRepository $utilisateurRepo, YamlFileRepository $yamlRepo, UtilisateurYamlFileRepertoireRepository $uyr)
    {
        $this->utilisateurRepo = $utilisateurRepo;
        $this->yamlRepo = $yamlRepo;
        $this->uyr = $uyr;
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
                'class' => UtilisateurYamlFileRepertoire::class,
                'choices' => $this->uyr->findYamlFilesForUserAdmin($utilisateur, $isAdmin),
                'choice_label' => function (UtilisateurYamlFileRepertoire $uyfr) use ($isAdmin) {

                    $file = $uyfr->getYamlFile();
                    $repertoire = $uyfr->getRepertoire();
                    $path = $repertoire->getFullPath();

                    if ($isAdmin) {
                        return sprintf(
                            '%s — %s %s',
                            $file->getNameFile(),
                            $file->getUtilisateurYamlfile()?->getLogin() . ' /' ?? 'inconnu',
                            $path
                        );
                    }

                    return sprintf(
                        '%s — %s',
                        $file->getNameFile(),
                        $path
                    );
                },

                'choice_value' => function (?UtilisateurYamlFileRepertoire $uyfr) {
                    return $uyfr ? $uyfr->getYamlFile()->getId() : '';
                },
                'label' => 'Fichier YAML à partager',
                'placeholder' => 'Sélectionner un fichier YAML',
            ]);
        $builder->get('yamlFile')->addModelTransformer(
            new CallbackTransformer(
                function ($yamlFile) {
                    return $yamlFile;
                },
                function ($uyfr) {
                    if ($uyfr instanceof UtilisateurYamlFileRepertoire) {
                        return $uyfr->getYamlFile();
                    }
                    return null;
                }
            )
        );
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

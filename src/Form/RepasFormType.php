<?php

namespace App\Form;

use App\Entity\Repas;
use App\Entity\User;
use App\Entity\RegimeAlimentaire;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;

class RepasFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $u) => $u->getEmail().' ('.$u->getFirstName().' '.$u->getLastName().')',
                'label' => 'Utilisateur',
                'placeholder' => '-- Sélectionner un utilisateur --',
            ])
            ->add('dateRepas', DateTimeType::class, [
                'widget' => 'single_text',
                'label' => 'Date et heure du repas',
                'input' => 'datetime_immutable',
                'constraints' => [new GreaterThanOrEqual('now')],
            ])
            ->add('typeRepas', ChoiceType::class, [
                'label' => 'Type de repas',
                'choices' => [
                    'Petit-déjeuner' => 'petit_de',
                    'Breakfast' => 'breakfast',
                    'Déjeuner' => 'dejeuner',
                    'Dîner' => 'diner',
                    'Evening' => 'evening',
                    'Collation' => 'collat',
                    'Extra meal' => 'extra_meal',
                ],
            ])
            ->add('nomRepas', TextType::class, [
                'label' => 'Nom du repas',
                'constraints' => [
                    new Regex(pattern: '/^[a-zA-Zàâäéèêëïîôùûüçœæ\s\'-]+$/u', message: 'Le nom ne doit contenir que des lettres.'),
                ],
            ])
            ->add('calories', IntegerType::class, [
                'label' => 'Calories',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit être strictement positif.')],
            ])
            ->add('proteines', IntegerType::class, [
                'label' => 'Protéines (g)',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit être strictement positif.')],
            ])
            ->add('glucides', IntegerType::class, [
                'label' => 'Glucides (g)',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit être strictement positif.')],
            ])
            ->add('lipides', IntegerType::class, [
                'label' => 'Lipides (g)',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit être strictement positif.')],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3],
                'constraints' => [
                    new Length(max: 20, maxMessage: 'Le commentaire ne doit pas dépasser 20 caractères.'),
                    new Regex(pattern: '/^[^<>]*$/u', message: 'Les balises HTML ne sont pas autorisées.'),
                ],
            ])
            ->add('regime', EntityType::class, [
                'class' => RegimeAlimentaire::class,
                'choice_label' => fn (RegimeAlimentaire $r) => 'Régime #'.$r->getId().' - '.($r->getTypeSante() ?? 'N/A').' - '.($r->getCaloriesCibles() ?? 0).' kcal',
                'label' => 'Régime associé',
                'required' => false,
                'placeholder' => '-- Aucun --',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repas::class,
        ]);
    }
}

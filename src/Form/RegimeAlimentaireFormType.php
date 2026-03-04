<?php

namespace App\Form;

use App\Entity\RegimeAlimentaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegimeAlimentaireFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('taille', NumberType::class, [
                'label' => 'Taille (cm)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('poids', NumberType::class, [
                'label' => 'Poids (kg)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'required' => false,
            ])
            ->add('bmi', NumberType::class, [
                'label' => 'IMC (BMI)',
                'required' => false,
                'scale' => 2,
            ])
            ->add('typeSante', ChoiceType::class, [
                'label' => 'Type de santé',
                'required' => false,
                'placeholder' => '-- Sélectionner --',
                'choices' => [
                    'Normal' => 'normal',
                    'Surpoids' => 'surpoids',
                    'Obésité' => 'obesite',
                    'Sous-poids' => 'sous_poids',
                    'Diabétique' => 'diabetique',
                    'Cardiaque' => 'cardiaque',
                    'Autre' => 'autre',
                ],
            ])
            ->add('caloriesCibles', IntegerType::class, [
                'label' => 'Calories cibles (kcal/jour)',
                'required' => false,
            ])
            ->add('repasAdequats', TextareaType::class, [
                'label' => 'Repas adéquats (suggestions)',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegimeAlimentaire::class,
        ]);
    }
}

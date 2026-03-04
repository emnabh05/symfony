<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('username', TextType::class)
            ->add('email', EmailType::class)
            ->add('phone', TextType::class)
            ->add('birthDate', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('gender', ChoiceType::class, [
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('avatarFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPG, PNG, WEBP, GIF).',
                    ]),
                ],
            ])
            ->add('height', NumberType::class, [
                'required' => false,
            ])
            ->add('weight', NumberType::class, [
                'required' => false,
            ])
            ->add('targetWeight', NumberType::class, [
                'required' => false,
            ])
            ->add('fitnessLevel', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Beginner' => 'beginner',
                    'Intermediate' => 'intermediate',
                    'Advanced' => 'advanced',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('healthConditions', TextareaType::class, [
                'required' => false,
            ])
            ->add('dietaryPreferences', ChoiceType::class, [
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Vegetarian' => 'vegetarian',
                    'Vegan' => 'vegan',
                    'Gluten-free' => 'gluten_free',
                    'Dairy-free' => 'dairy_free',
                    'Halal' => 'halal',
                    'Keto' => 'keto',
                ],
            ])
            ->add('fitnessGoals', ChoiceType::class, [
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Weight loss' => 'weight_loss',
                    'Muscle gain' => 'muscle_gain',
                    'Endurance' => 'endurance',
                    'Flexibility' => 'flexibility',
                    'Maintenance' => 'maintenance',
                ],
            ])
            ->add('professionalTitle', TextType::class, [
                'required' => false,
            ])
            ->add('specialization', ChoiceType::class, [
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'choices' => [
                    'Weight Loss' => 'weight_loss',
                    'Strength Training' => 'strength_training',
                    'Yoga' => 'yoga',
                    'Nutrition' => 'nutrition',
                    'Rehab' => 'rehab',
                ],
            ])
            ->add('qualification', TextType::class, [
                'required' => false,
            ])
            ->add('yearsOfExperience', IntegerType::class, [
                'required' => false,
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
            ])
            ->add('licenseNumber', TextType::class, [
                'required' => false,
            ]);

        $healthConditionsTransformer = new CallbackTransformer(
            fn (?array $values): string => $values ? implode(', ', $values) : '',
            fn (?string $value): array => $value
                ? array_values(array_filter(array_map('trim', explode(',', $value))))
                : []
        );

        $builder->get('healthConditions')->addModelTransformer($healthConditionsTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

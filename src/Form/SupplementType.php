<?php

namespace App\Form;

use App\Entity\Supplement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class SupplementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter supplement name'],
            ])
            ->add('category', ChoiceType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Select a category',
                'choices' => [
                    'Protein' => 'Protein',
                    'Gainer' => 'Gainer',
                    'Creatine' => 'Creatine',
                    'Pre-Workout' => 'Pre-Workout',
                    'BCAA' => 'BCAA',
                    'Multivitamin' => 'Multivitamin',
                    'Omega-3' => 'Omega-3',
                    'Fat Burner' => 'Fat Burner',
                    'Amino Acids' => 'Amino Acids',
                    'Energy Drink' => 'Energy Drink',
                    'Protein Bar' => 'Protein Bar',
                    'Glutamine' => 'Glutamine',
                    'ZMA' => 'ZMA',
                    'Testosterone Booster' => 'Testosterone Booster',
                    'Post-Workout' => 'Post-Workout',
                ],
            ])
            ->add('brand', ChoiceType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Select a brand',
                'choices' => [
                    'Optimum Nutrition' => 'Optimum Nutrition',
                    'MyProtein' => 'MyProtein',
                    'BSN' => 'BSN',
                    'MuscleTech' => 'MuscleTech',
                    'Dymatize' => 'Dymatize',
                    'Cellucor' => 'Cellucor',
                    'Universal Nutrition' => 'Universal Nutrition',
                    'Scitec Nutrition' => 'Scitec Nutrition',
                    'Mutant' => 'Mutant',
                    'Nutrex' => 'Nutrex',
                    'BPI Sports' => 'BPI Sports',
                    'GAT Sport' => 'GAT Sport',
                    'MHP' => 'MHP',
                    'Ronnie Coleman' => 'Ronnie Coleman',
                    'Evlution Nutrition' => 'Evlution Nutrition',
                    'JNX Sports' => 'JNX Sports',
                    'Redcon1' => 'Redcon1',
                    'Ghost' => 'Ghost',
                    'C4' => 'C4',
                    'Transparent Labs' => 'Transparent Labs',
                    'Other' => 'Other',
                ],
            ])
            ->add('price', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00'],
            ])
            ->add('stock', IntegerType::class, [
                'required' => false,
                'invalid_message' => 'Stock must be a whole number.',
                'attr' => ['class' => 'form-control', 'placeholder' => '0'],
            ])
            ->add('calories', IntegerType::class, [
                'required' => false,
                'invalid_message' => 'Calories must be a whole number.',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Optional'],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5, 'placeholder' => 'Enter supplement description...'],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Product Image (JPG, PNG, WEBP)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Image(
                        maxSize: '5M',
                        maxSizeMessage: 'Image size must not exceed 5MB.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Only JPG, PNG, and WEBP images are allowed.'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Supplement::class,
            'csrf_protection' => true,
        ]);
    }
}

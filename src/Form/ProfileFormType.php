<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProfileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'First name is required.'])],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Last name is required.'])],
            ])
            ->add('username', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Username is required.'])],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Email is required.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                ],
            ])
            ->add('phone', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Phone is required.'])],
            ])
            ->add('birthDate', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

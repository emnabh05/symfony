<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
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
                'constraints' => [new NotBlank(['message' => 'Birth date is required.'])],
            ])
            ->add('role', ChoiceType::class, [
                'mapped' => false,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'Patient' => 'ROLE_PATIENT',
                    'Coach' => 'ROLE_COACH',
                    'Nutritionist' => 'ROLE_NUTRITIONIST',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'data' => 'ROLE_PATIENT',
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Password is required.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-z])(?=.*\d).+$/',
                        'message' => 'Password must contain at least one letter and one number.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

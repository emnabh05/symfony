<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Email is required.']),
                    new Email(['message' => 'Please enter a valid email address.']),
                ],
            ])
            ->add('username', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Username is required.'])],
            ])
            ->add('firstName', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'First name is required.'])],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Last name is required.'])],
            ])
            ->add('phone', TextType::class, [
                'constraints' => [new NotBlank(['message' => 'Phone is required.'])],
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
            ])
            ->add('birthDate', DateType::class, [
                'widget' => 'single_text',
                'constraints' => [new NotBlank(['message' => 'Birth date is required.'])],
            ])
            ->add('gender', ChoiceType::class, [
                'choices' => [
                    'Male' => 'male',
                    'Female' => 'female',
                ],
                'expanded' => true,
                'multiple' => false,
                'constraints' => [new NotBlank(['message' => 'Gender is required.'])],
            ])
            ->add('avatarFile', FileType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Avatar profile picture is required.']),
                    new File([
                        'maxSize' => '2M',
                    ]),
                ],
            ])
            ->add('height', NumberType::class, [
                'required' => false,
                'constraints' => [new Positive(['message' => 'Height must be a positive number.'])],
            ])
            ->add('weight', NumberType::class, [
                'required' => false,
                'constraints' => [new Positive(['message' => 'Weight must be a positive number.'])],
            ])
            ->add('targetWeight', NumberType::class, [
                'required' => false,
                'constraints' => [new Positive(['message' => 'Target weight must be a positive number.'])],
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
                'constraints' => [
                    new GreaterThanOrEqual(['value' => 0, 'message' => 'Years of experience must be 0 or more.']),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'required' => false,
            ])
            ->add('licenseNumber', TextType::class, [
                'required' => false,
            ])

            // NOT mapped to entity
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Password is required.']),
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*[A-Za-z])(?=.*\\d).+$/',
                        'message' => 'Password must contain at least one letter and one number.',
                    ]),
                ],
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
            'constraints' => [
                new Callback([$this, 'validateRoleSpecific']),
            ],
        ]);
    }

    public function validateRoleSpecific(User $user, ExecutionContextInterface $context): void
    {
        $form = $context->getRoot();
        $role = $form->has('role') ? $form->get('role')->getData() : null;

        $email = $user->getEmail();
        if ($role === 'ROLE_ADMIN' && $email && !preg_match('/@fitopia\\.com$/i', $email)) {
            $context->buildViolation('Admin email must end with @fitopia.com.')
                ->atPath('email')
                ->addViolation();
        }
        if ($role === 'ROLE_PATIENT' && $email && !preg_match('/@(gmail\\.com|fitopia\\.com)$/i', $email)) {
            $context->buildViolation('Patient email must be @gmail.com or @fitopia.com.')
                ->atPath('email')
                ->addViolation();
        }

        $isPatient = $role === 'ROLE_PATIENT';
        $isPro = in_array($role, ['ROLE_COACH', 'ROLE_NUTRITIONIST', 'ROLE_ADMIN'], true);

        if ($isPatient) {
            if ($user->getHeight() === null) {
                $context->buildViolation('Height is required.')->atPath('height')->addViolation();
            }
            if ($user->getWeight() === null) {
                $context->buildViolation('Weight is required.')->atPath('weight')->addViolation();
            }
            if ($user->getTargetWeight() === null) {
                $context->buildViolation('Target weight is required.')->atPath('targetWeight')->addViolation();
            }
            if (!$user->getFitnessLevel()) {
                $context->buildViolation('Fitness level is required.')->atPath('fitnessLevel')->addViolation();
            }
            if (!$user->getHealthConditions() || count($user->getHealthConditions()) === 0) {
                $context->buildViolation('Health conditions are required.')->atPath('healthConditions')->addViolation();
            }
            if (!$user->getDietaryPreferences() || count($user->getDietaryPreferences()) === 0) {
                $context->buildViolation('Select at least one dietary preference.')->atPath('dietaryPreferences')->addViolation();
            }
            if (!$user->getFitnessGoals() || count($user->getFitnessGoals()) === 0) {
                $context->buildViolation('Select at least one fitness goal.')->atPath('fitnessGoals')->addViolation();
            }
        }

        if ($isPro) {
            if (!$user->getProfessionalTitle()) {
                $context->buildViolation('Professional title is required.')->atPath('professionalTitle')->addViolation();
            }
            if (!$user->getSpecialization() || count($user->getSpecialization()) === 0) {
                $context->buildViolation('Select at least one specialization.')->atPath('specialization')->addViolation();
            }
            if (!$user->getQualification()) {
                $context->buildViolation('Qualification is required.')->atPath('qualification')->addViolation();
            }
            if ($user->getYearsOfExperience() === null) {
                $context->buildViolation('Years of experience is required.')->atPath('yearsOfExperience')->addViolation();
            }
            if (!$user->getBio()) {
                $context->buildViolation('Bio is required.')->atPath('bio')->addViolation();
            }
            if (!$user->getLicenseNumber()) {
                $context->buildViolation('License number is required.')->atPath('licenseNumber')->addViolation();
            }
        }
    }
}

<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class EventReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomParticipant', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('prenomParticipant', TextType::class, [
                'label' => 'Prenom',
                'mapped' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('emailParticipant', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'L email est obligatoire.']),
                ],
            ])
            ->add('telephoneParticipant', TextType::class, [
                'label' => 'Telephone',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+216XXXXXXXX',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le telephone est obligatoire.']),
                    new Length(['max' => 30]),
                    new Regex([
                        'pattern' => '/^(\+216|216)?[0-9]{8}$/',
                        'message' => 'Le telephone doit etre au format +216XXXXXXXX, 216XXXXXXXX ou XXXXXXXX.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
            'csrf_field_name' => '_token',
        ]);
    }
}

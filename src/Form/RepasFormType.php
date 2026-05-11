<?php

namespace App\Form;

use App\Entity\RegimeAlimentaire;
use App\Entity\Repas;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Regex;

class RepasFormType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $u) => $u->getEmail().' ('.$u->getFirstName().' '.$u->getLastName().')',
                'label' => 'Utilisateur',
                'placeholder' => '-- Selectionner un utilisateur --',
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
                    'Petit-dejeuner' => 'petit_de',
                    'Breakfast' => 'breakfast',
                    'Dejeuner' => 'dejeuner',
                    'Diner' => 'diner',
                    'Evening' => 'evening',
                    'Collation' => 'collat',
                    'Extra meal' => 'extra_meal',
                ],
            ])
            ->add('nomRepas', TextType::class, [
                'label' => 'Nom du repas',
                'constraints' => [
                    new Regex(pattern: "/^[a-zA-ZÀ-ÖØ-öø-ÿ\\s'-]+$/u", message: 'Le nom ne doit contenir que des lettres.'),
                ],
            ])
            ->add('calories', IntegerType::class, [
                'label' => 'Calories',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit etre strictement positif.')],
            ])
            ->add('proteines', IntegerType::class, [
                'label' => 'Proteines (g)',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit etre strictement positif.')],
            ])
            ->add('glucides', IntegerType::class, [
                'label' => 'Glucides (g)',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit etre strictement positif.')],
            ])
            ->add('lipides', IntegerType::class, [
                'label' => 'Lipides (g)',
                'required' => false,
                'constraints' => [new Positive(message: 'Doit etre strictement positif.')],
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => ['rows' => 3],
                'constraints' => [
                    new Length(max: 20, maxMessage: 'Le commentaire ne doit pas depasser 20 caracteres.'),
                    new Regex(pattern: '/^[^<>]*$/u', message: 'Les balises HTML ne sont pas autorisees.'),
                ],
            ]);

        $this->addRegimeField($builder, null);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $userId = null;
            $selectedRegimeId = null;

            if ($data instanceof Repas) {
                $userId = $data->getUser()?->getId();
                $selectedRegimeId = $data->getRegime()?->getId();
            }

            $this->addRegimeField($event->getForm(), $userId, $selectedRegimeId);
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                $this->addRegimeField($event->getForm(), null);
                return;
            }

            $userId = isset($data['user']) && $data['user'] !== '' ? (int) $data['user'] : null;
            $selectedRegimeId = isset($data['regime']) && $data['regime'] !== '' ? (int) $data['regime'] : null;

            $this->addRegimeField($event->getForm(), $userId, $selectedRegimeId);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repas::class,
        ]);
    }

    private function addRegimeField(FormInterface|FormBuilderInterface $form, ?int $userId, ?int $selectedRegimeId = null): void
    {
        $regimes = [];

        if ($userId !== null && $userId > 0) {
            $regimes = $this->entityManager->getRepository(RegimeAlimentaire::class)->findBy(
                ['user' => $userId],
                ['id' => 'DESC']
            );
        }

        if ($selectedRegimeId !== null && $selectedRegimeId > 0) {
            $selectedRegime = $this->entityManager->getRepository(RegimeAlimentaire::class)->find($selectedRegimeId);
            if (
                $selectedRegime instanceof RegimeAlimentaire
                && ($userId === null || $selectedRegime->getUser()?->getId() === $userId)
            ) {
                $alreadyLoaded = false;
                foreach ($regimes as $regime) {
                    if ($regime->getId() === $selectedRegime->getId()) {
                        $alreadyLoaded = true;
                        break;
                    }
                }

                if (!$alreadyLoaded) {
                    $regimes[] = $selectedRegime;
                }
            }
        }

        $form->add('regime', EntityType::class, [
            'class' => RegimeAlimentaire::class,
            'choices' => $regimes,
            'choice_label' => fn (RegimeAlimentaire $r) => 'Regime #'.$r->getId().' - '.($r->getTypeSante() ?? 'N/A').' - '.($r->getCaloriesCibles() ?? 0).' kcal',
            'label' => 'Regime associe',
            'required' => false,
            'placeholder' => $userId ? '-- Choisir un regime lie a cet utilisateur --' : '-- Choisir d abord un utilisateur --',
        ]);
    }
}

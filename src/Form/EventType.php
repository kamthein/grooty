<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\Guardian;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $guardianChoices = array_map(fn($cg) => $cg->getGuardian(), $options['guardians']);

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr'  => ['placeholder' => 'Ex : Cours de natation, RDV pédiatre…'],
            ])
            ->add('type', ChoiceType::class, [
                'label'   => 'Type',
                'choices' => [
                    '🏠 Garde'      => Event::TYPE_GARDE,
                    '🏃 Activité'   => Event::TYPE_ACTIVITE,
                    '🏥 Médical'    => Event::TYPE_MEDICAL,
                    '🌴 Vacances'   => Event::TYPE_VACANCES,
                    '📌 Autre'      => Event::TYPE_AUTRE,
                ],
                'expanded' => true,
            ])
            ->add('startAt', DateTimeType::class, [
                'label'        => 'Début',
                'widget'       => 'single_text',
                'html5'        => true,
            ])
            ->add('endAt', DateTimeType::class, [
                'label'    => 'Fin',
                'widget'   => 'single_text',
                'html5'    => true,
                'required' => false,
            ])
            ->add('allDay', CheckboxType::class, [
                'label'    => 'Toute la journée',
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 3, 'placeholder' => 'Informations utiles, adresse…'],
            ])
            ->add('recurrence', ChoiceType::class, [
                'label'   => 'Récurrence',
                'choices' => [
                    'Aucune'         => Event::RECURRENCE_NONE,
                    'Chaque jour'    => Event::RECURRENCE_DAILY,
                    'Chaque semaine' => Event::RECURRENCE_WEEKLY,
                    'Chaque mois'    => Event::RECURRENCE_MONTHLY,
                ],
            ])
            ->add('responsibleGuardian', EntityType::class, [
                'label'        => 'Gardien responsable',
                'class'        => Guardian::class,
                'choices'      => $guardianChoices,
                'choice_label' => fn(Guardian $g) => $g->getFullName(),
                'expanded'     => true,
                'multiple'     => false,
                'required'     => false,
                'placeholder'  => false,
            ])
            ->add('visibleTo', ChoiceType::class, [
                'label'    => 'Visible par',
                'choices'  => array_combine(
                    array_map(fn($g) => $g->getFullName(), $guardianChoices),
                    array_map(fn($g) => $g->getId(), $guardianChoices)
                ),
                'expanded' => true,
                'multiple' => true,
                'required' => false,
                'mapped'   => false,
                'data'     => $options['visible_to'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Event::class,
            'guardians'       => [],
            'visible_to'      => [],
            'csrf_protection' => false,
        ]);
    }
}

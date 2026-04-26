<?php
namespace App\Form;

use App\Entity\Child;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['placeholder' => 'Léa, Tom…'],
            ])
            ->add('birthDate', BirthdayType::class, [
                'label'    => 'Date de naissance',
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('theme', ChoiceType::class, [
                'label'   => 'Thème du calendrier',
                'choices' => [
                    '🚂 Petit train' => 'train',
                    '🎀 Hello Kitty' => 'kitty',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('avatarFile', FileType::class, [
                'label'    => 'Photo (optionnel)',
                'mapped'   => false,
                'required' => false,
                'constraints' => [new Image(['maxSize' => '5M'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Child::class,
            'csrf_protection' => false,
        ]);
    }
}

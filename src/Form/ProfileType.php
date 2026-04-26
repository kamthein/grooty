<?php

namespace App\Form;

use App\Entity\Guardian;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Prénom'])
            ->add('lastName',  TextType::class, ['label' => 'Nom'])
            ->add('email',     EmailType::class, ['label' => 'Email'])
            ->add('newPassword', PasswordType::class, [
                'label'       => 'Nouveau mot de passe (laisser vide pour ne pas changer)',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [new Length(['min' => 8, 'minMessage' => '8 caractères minimum.'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => Guardian::class,
            'csrf_protection' => false,
        ]);
    }
}

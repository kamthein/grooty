<?php
namespace App\Form;

use App\Entity\ChildGuardian;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class InviteGuardianType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label'       => 'Email du gardien',
                'constraints' => [new NotBlank(), new Email()],
                'attr'        => ['placeholder' => 'papa@email.fr'],
            ])
            ->add('role', ChoiceType::class, [
                'label'   => 'Rôle',
                'choices' => [
                    'Parent'       => ChildGuardian::ROLE_PARENT,
                    'Nounou'       => ChildGuardian::ROLE_NOUNOU,
                    'Grand-parent' => ChildGuardian::ROLE_GRANDPARENT,
                    'Autre'        => ChildGuardian::ROLE_OTHER,
                ],
            ])
            ->add('permission', ChoiceType::class, [
                'label'   => 'Permissions',
                'choices' => [
                    'Peut modifier (créer événements/notes)' => ChildGuardian::PERM_EDIT,
                    'Lecture seule'                          => ChildGuardian::PERM_VIEW,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}

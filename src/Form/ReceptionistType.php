<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ReceptionistType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Full name'])
            ->add('email', EmailType::class, ['label' => 'Login email'])
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'Clinic admin (can manage doctors, staff and settings)',
                'required' => false,
                'mapped' => false,
                'data' => $options['is_admin'],
            ])
            ->add('isActive', CheckboxType::class, ['label' => 'Active', 'required' => false])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['password_required'] ? 'Password' : 'New password (leave empty to keep current)',
                'mapped' => false,
                'required' => $options['password_required'],
                'constraints' => array_filter([
                    $options['password_required'] ? new Assert\NotBlank(message: 'Please set a password.') : null,
                    new Assert\Length(min: 10, minMessage: 'Use at least {{ limit }} characters.', allowEmptyString: true),
                ]),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_admin' => false,
            'password_required' => true,
        ]);
    }
}

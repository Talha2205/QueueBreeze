<?php

namespace App\Form;

use App\Entity\Doctor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DoctorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Doctor name'])
            ->add('specialty', TextType::class, ['label' => 'Specialty', 'required' => false])
            ->add('tokenPrefix', TextType::class, [
                'label' => 'Token prefix (one letter)',
                'constraints' => [new Assert\Regex(pattern: '/^[A-Za-z]$/', message: 'Use a single letter, for example A.')],
            ])
            ->add('avgConsultationMinutes', IntegerType::class, ['label' => 'Average consultation (minutes)', 'constraints' => [new Assert\Range(min: 1, max: 240)]])
            ->add('isAvailableToday', CheckboxType::class, ['label' => 'Accepting tokens today', 'required' => false])
            ->add('isActive', CheckboxType::class, ['label' => 'Active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Doctor::class]);
    }
}

<?php

namespace App\Form;

use App\Entity\Clinic;
use App\Enum\NotificationProvider;
use App\Enum\PriorityRule;
use App\Enum\RecallPlacement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ClinicSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Clinic name'])
            ->add('address', TextType::class, ['label' => 'Address', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Phone', 'required' => false])
            ->add('notifyThreshold', IntegerType::class, ['label' => 'Send “approaching” message when patient is within this many places', 'constraints' => [new Assert\Range(min: 1, max: 20)]])
            ->add('priorityRule', EnumType::class, ['class' => PriorityRule::class, 'label' => 'Priority placement', 'choice_label' => static fn (PriorityRule $rule): string => $rule->label()])
            ->add('priorityAfterN', IntegerType::class, ['label' => 'N (for “after N waiting patients”)', 'constraints' => [new Assert\Range(min: 0, max: 50)]])
            ->add('recallPlacement', EnumType::class, ['class' => RecallPlacement::class, 'label' => 'Recalled patients go to', 'choice_label' => static fn (RecallPlacement $placement): string => $placement->label()])
            ->add('preferCheckedIn', CheckboxType::class, ['label' => 'Call checked-in patients before others', 'required' => false])
            ->add('waitRangeBufferPercent', IntegerType::class, ['label' => 'Wait-time range buffer (%)', 'constraints' => [new Assert\Range(min: 0, max: 100)]])
            ->add('notificationProvider', EnumType::class, ['class' => NotificationProvider::class, 'label' => 'Notification provider', 'choice_label' => static fn (NotificationProvider $provider): string => $provider->label()]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Clinic::class]);
    }
}

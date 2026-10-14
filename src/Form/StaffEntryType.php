<?php

namespace App\Form;

use App\Dto\StaffEntryRequest;
use App\Enum\NotificationChannel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class StaffEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Patient name'])
            ->add('phone', TextType::class, ['label' => 'Mobile number (optional)', 'required' => false, 'attr' => ['placeholder' => '3001234567']])
            ->add('channel', EnumType::class, [
                'class' => NotificationChannel::class,
                'expanded' => true,
                'required' => false,
                'label' => 'Notify via',
                'choice_label' => static fn (NotificationChannel $channel): string => 'WhatsApp' === $channel->name ? 'WhatsApp' : 'SMS',
            ]);

        if ($options['with_priority_reason']) {
            $builder->add('priorityReason', TextType::class, ['label' => 'Reason for priority (staff only)']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StaffEntryRequest::class,
            'with_priority_reason' => false,
            'validation_groups' => static fn (\Symfony\Component\Form\FormInterface $form): array => $form->getConfig()->getOption('with_priority_reason') ? ['Default', 'priority'] : ['Default'],
        ]);
        $resolver->setAllowedTypes('with_priority_reason', 'bool');
    }
}

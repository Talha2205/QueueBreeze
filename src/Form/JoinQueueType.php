<?php

namespace App\Form;

use App\Dto\JoinQueueRequest;
use App\Enum\NotificationChannel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class JoinQueueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('doctorId', HiddenType::class)
            ->add('name', TextType::class, ['label' => 'Full name'])
            ->add('phone', TextType::class, ['label' => 'Mobile number', 'attr' => ['inputmode' => 'tel', 'autocomplete' => 'tel-national', 'placeholder' => '3001234567']])
            ->add('channel', EnumType::class, [
                'class' => NotificationChannel::class,
                'expanded' => true,
                'label' => 'Send updates via',
                'choice_label' => static fn (NotificationChannel $channel): string => 'WhatsApp' === $channel->name ? 'WhatsApp' : 'SMS',
            ])
            ->add('website', TextType::class, ['required' => false, 'label' => false, 'attr' => ['tabindex' => '-1', 'autocomplete' => 'off', 'class' => 'honeypot-field']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JoinQueueRequest::class]);
    }
}

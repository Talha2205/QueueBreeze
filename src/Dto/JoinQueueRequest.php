<?php

namespace App\Dto;

use App\Enum\NotificationChannel;
use App\Service\Phone\PakistaniPhone;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class JoinQueueRequest
{
    #[Assert\NotNull(message: 'Please choose a doctor.')]
    public ?int $doctorId = null;

    #[Assert\NotBlank(message: 'Please enter your name.')]
    #[Assert\Length(max: 120, maxMessage: 'Please keep your name under {{ limit }} characters.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Please enter your mobile number.')]
    public string $phone = '';

    #[Assert\NotNull(message: 'Please choose SMS or WhatsApp.')]
    public ?NotificationChannel $channel = null;

    /** Honeypot field: real visitors never fill this in. */
    public string $website = '';

    public function normalizedPhone(): ?string
    {
        return PakistaniPhone::normalize($this->phone);
    }

    #[Assert\Callback]
    public function validatePhone(ExecutionContextInterface $context): void
    {
        if ('' !== $this->phone && null === $this->normalizedPhone()) {
            $context->buildViolation('Enter a valid Pakistani mobile number, for example 3001234567.')
                ->atPath('phone')
                ->addViolation();
        }
    }
}

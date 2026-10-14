<?php

namespace App\Dto;

use App\Enum\NotificationChannel;
use App\Service\Phone\PakistaniPhone;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class StaffEntryRequest
{
    #[Assert\NotBlank(message: 'Please enter the patient name.')]
    #[Assert\Length(max: 120, maxMessage: 'Please keep the name under {{ limit }} characters.')]
    public string $name = '';

    public string $phone = '';

    public ?NotificationChannel $channel = null;

    #[Assert\NotBlank(message: 'Please give a short reason for the priority.', groups: ['priority'])]
    #[Assert\Length(max: 255, maxMessage: 'Please keep the reason under {{ limit }} characters.')]
    public string $priorityReason = '';

    #[Assert\Callback]
    public function validateContact(ExecutionContextInterface $context): void
    {
        if ('' === $this->phone) {
            return;
        }

        if (null === PakistaniPhone::normalize($this->phone)) {
            $context->buildViolation('Enter a valid Pakistani mobile number, or leave it empty.')
                ->atPath('phone')
                ->addViolation();
        }

        if (null === $this->channel) {
            $context->buildViolation('Choose SMS or WhatsApp for notifications.')
                ->atPath('channel')
                ->addViolation();
        }
    }

    public function normalizedPhone(): ?string
    {
        return '' === $this->phone ? null : PakistaniPhone::normalize($this->phone);
    }
}

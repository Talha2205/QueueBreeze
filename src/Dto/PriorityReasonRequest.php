<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PriorityReasonRequest
{
    #[Assert\NotBlank(message: 'Please give a short reason for the priority.')]
    #[Assert\Length(max: 255, maxMessage: 'Please keep the reason under {{ limit }} characters.')]
    public string $priorityReason = '';
}

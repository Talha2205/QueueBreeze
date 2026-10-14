<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'DoctorCard')]
final class DoctorCard
{
    /** @var array{doctor: \App\Entity\Doctor, nowServing: ?string, waitingCount: int, waitText: string} */
    public array $summary;
}

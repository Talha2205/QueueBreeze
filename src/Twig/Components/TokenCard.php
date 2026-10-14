<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'TokenCard')]
final class TokenCard
{
    public string $token;
    public string $doctorName;
}

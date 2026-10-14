<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'StatCard')]
final class StatCard
{
    public string $label;
    public string|int|float $value;
    public ?string $hint = null;
}

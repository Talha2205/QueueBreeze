<?php

namespace App\Twig;

use App\Service\Phone\PakistaniPhone;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('mask_phone', [PakistaniPhone::class, 'mask']),
            new TwigFilter('format_phone', [PakistaniPhone::class, 'format']),
        ];
    }
}

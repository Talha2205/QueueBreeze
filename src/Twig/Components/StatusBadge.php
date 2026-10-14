<?php

namespace App\Twig\Components;

use App\Enum\QueueStatus;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(name: 'StatusBadge')]
final class StatusBadge
{
    public QueueStatus $status;

    public function classes(): string
    {
        return match ($this->status) {
            QueueStatus::WAITING => 'bg-amber-100 text-amber-900 ring-amber-300',
            QueueStatus::CALLED => 'bg-blue-100 text-blue-900 ring-blue-300',
            QueueStatus::IN_PROGRESS => 'bg-violet-100 text-violet-900 ring-violet-300',
            QueueStatus::COMPLETED => 'bg-emerald-100 text-emerald-900 ring-emerald-300',
            QueueStatus::SKIPPED => 'bg-slate-200 text-slate-800 ring-slate-400',
            QueueStatus::CANCELLED => 'bg-rose-100 text-rose-900 ring-rose-300',
        };
    }
}

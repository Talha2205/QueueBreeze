<?php

namespace App\Service\Queue;

final class WaitTimeCalculator
{
    private const ROUNDING_MINUTES = 5;

    /** @return array{0: int, 1: int} low and high estimates in minutes */
    public function range(int $patientsAhead, int $avgConsultationMinutes, int $bufferPercent): array
    {
        $estimate = $patientsAhead * $avgConsultationMinutes;
        $low = $this->roundToStep($estimate);
        $high = $this->roundToStep($estimate * (100 + $bufferPercent) / 100);

        return [$low, max($low, $high)];
    }

    public function format(int $low, int $high): string
    {
        if ($low <= 0 && $high <= 0) {
            return "You're next";
        }

        if ($low === $high) {
            return \sprintf('about %d minutes', $low);
        }

        return \sprintf('%d–%d minutes', $low, $high);
    }

    private function roundToStep(float|int $minutes): int
    {
        return (int) (self::ROUNDING_MINUTES * round($minutes / self::ROUNDING_MINUTES));
    }
}

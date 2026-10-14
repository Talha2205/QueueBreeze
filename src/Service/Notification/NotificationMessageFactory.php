<?php

namespace App\Service\Notification;

use App\Entity\QueueEntry;
use App\Enum\NotificationType;
use App\Service\Queue\QueueReader;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class NotificationMessageFactory
{
    public function __construct(
        private readonly QueueReader $reader,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function build(QueueEntry $entry, NotificationType $type): string
    {
        $status = $this->reader->patientStatus($entry);
        $doctorName = $entry->getDoctor()->getName();

        return match ($type) {
            NotificationType::JOINED => \sprintf(
                'QueueBreeze: Token %s confirmed with %s. You are %s in line. Estimated wait: %s. Track your token: %s',
                $entry->getTokenNumber(),
                $doctorName,
                $this->ordinal($status['position'] ?? 0),
                $status['waitText'],
                $this->trackingUrl($entry),
            ),
            NotificationType::APPROACHING => \sprintf(
                'Your turn is approaching. You are currently %s in line. Please return to the clinic. Token %s.',
                $this->ordinal($status['position'] ?? 0),
                $entry->getTokenNumber(),
            ),
            NotificationType::CALLED => \sprintf(
                "Token %s, please proceed to %s's room.",
                $entry->getTokenNumber(),
                $doctorName,
            ),
        };
    }

    private function trackingUrl(QueueEntry $entry): string
    {
        return $this->urlGenerator->generate('patient_token', ['publicId' => $entry->getPublicId()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function ordinal(int $number): string
    {
        $suffix = match (true) {
            \in_array($number % 100, [11, 12, 13], true) => 'th',
            1 === $number % 10 => 'st',
            2 === $number % 10 => 'nd',
            3 === $number % 10 => 'rd',
            default => 'th',
        };

        return $number.$suffix;
    }
}

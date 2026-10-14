<?php

namespace App\Service\Security;

use App\Entity\QueueEntry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QrSigner
{
    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function checkInUrl(QueueEntry $entry): string
    {
        return $this->urlGenerator->generate('staff_check_in_scan', [
            'token' => $entry->getPublicId(),
            'sig' => $this->sign($entry->getPublicId(), $entry->getClinic()->getId(), $entry->getQueueDate()),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function isValid(string $publicId, int $clinicId, \DateTimeImmutable $queueDate, string $signature): bool
    {
        return hash_equals($this->sign($publicId, $clinicId, $queueDate), $signature);
    }

    private function sign(string $publicId, ?int $clinicId, \DateTimeImmutable $queueDate): string
    {
        $payload = implode('|', [$publicId, (string) $clinicId, $queueDate->format('Y-m-d')]);

        return substr(hash_hmac('sha256', $payload, $this->secret), 0, 32);
    }
}

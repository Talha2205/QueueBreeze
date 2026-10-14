<?php

namespace App\Service\Realtime;

use App\Entity\Doctor;
use App\Entity\QueueEntry;
use App\Service\Queue\QueueReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

final class QueueBroadcaster
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly Environment $twig,
        private readonly QueueReader $reader,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function patientTopic(string $publicId): string
    {
        return 'patient/'.$publicId;
    }

    public static function doctorTopic(int $doctorId): string
    {
        return 'staff/doctor/'.$doctorId.'/queue';
    }

    public function renderPatient(QueueEntry $entry): string
    {
        return $this->twig->render('patient/_live_stream.html.twig', [
            'entry' => $entry,
            'status' => $this->reader->patientStatus($entry),
        ]);
    }

    public function renderDoctor(Doctor $doctor, \DateTimeImmutable $date): string
    {
        return $this->twig->render('staff/_doctor_stream.html.twig', [
            'doctor' => $doctor,
            'summary' => $this->reader->doctorSummary($doctor, $date),
            'rows' => $this->reader->doctorRows($doctor, $date),
        ]);
    }

    public function publishPatient(QueueEntry $entry): void
    {
        $this->publish(new Update(self::patientTopic($entry->getPublicId()), $this->renderPatient($entry), false));
    }

    public function publishDoctor(Doctor $doctor, \DateTimeImmutable $date): void
    {
        $this->publish(new Update(self::doctorTopic($doctor->getId()), $this->renderDoctor($doctor, $date), true));
    }

    private function publish(Update $update): void
    {
        try {
            $this->hub->publish($update);
        } catch (\Throwable $exception) {
            $this->logger->warning('Live update could not be published; clients will fall back to polling.', [
                'topic' => $update->getTopics()[0] ?? null,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

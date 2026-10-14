<?php

namespace App\EventSubscriber;

use App\Event\QueueChangedEvent;
use App\Repository\DoctorRepository;
use App\Repository\QueueEntryRepository;
use App\Service\Queue\QueueDateProvider;
use App\Service\Realtime\QueueBroadcaster;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: QueueChangedEvent::class)]
final class QueueBroadcastSubscriber
{
    public function __construct(
        private readonly DoctorRepository $doctors,
        private readonly QueueEntryRepository $entries,
        private readonly QueueDateProvider $dates,
        private readonly QueueBroadcaster $broadcaster,
    ) {
    }

    public function __invoke(QueueChangedEvent $event): void
    {
        $doctor = $this->doctors->find($event->doctorId);
        if (null === $doctor) {
            return;
        }

        $date = $this->dates->today();
        $this->broadcaster->publishDoctor($doctor, $date);

        foreach ($this->entries->findForDoctorOnDate($doctor, $date) as $entry) {
            $this->broadcaster->publishPatient($entry);
        }
    }
}

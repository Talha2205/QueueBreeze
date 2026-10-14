<?php

namespace App\Controller\Staff;

use App\Dto\PriorityReasonRequest;
use App\Dto\StaffEntryRequest;
use App\Entity\Doctor;
use App\Entity\QueueEntry;
use App\Entity\User;
use App\Exception\QueueException;
use App\Form\StaffEntryType;
use App\Service\Queue\QueueDateProvider;
use App\Service\Queue\QueueReader;
use App\Service\Queue\QueueService;
use App\Service\Realtime\QueueBroadcaster;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class QueueController extends AbstractController
{
    private const ENTRY_ACTIONS = ['call', 'start', 'complete', 'skip', 'recall', 'cancel', 'check-in'];

    public function __construct(
        private readonly QueueService $queue,
        private readonly QueueReader $reader,
        private readonly QueueDateProvider $dates,
        private readonly QueueBroadcaster $broadcaster,
        #[Autowire('%app.mercure_public_url%')] private readonly string $mercurePublicUrl,
    ) {
    }

    #[Route('/staff/doctor/{id}', name: 'staff_doctor_queue', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Doctor $doctor, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($doctor, $user);
        $date = $this->dates->today();

        return $this->render('staff/doctor_queue.html.twig', [
            'doctor' => $doctor,
            'summary' => $this->reader->doctorSummary($doctor, $date),
            'rows' => $this->reader->doctorRows($doctor, $date),
            'hubUrl' => $this->mercurePublicUrl,
            'topic' => QueueBroadcaster::doctorTopic($doctor->getId()),
        ]);
    }

    #[Route('/staff/doctor/{id}/live', name: 'staff_doctor_live', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function live(Doctor $doctor, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($doctor, $user);

        return new Response($this->broadcaster->renderDoctor($doctor, $this->dates->today()), headers: ['Content-Type' => 'text/vnd.turbo-stream.html']);
    }

    #[Route('/staff/doctor/{id}/call-next', name: 'staff_call_next', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function callNext(Request $request, Doctor $doctor, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($doctor, $user);

        if ($this->isCsrfTokenValid('call-next-'.$doctor->getId(), (string) $request->request->get('_token'))) {
            $this->attempt(function () use ($doctor, $user): void {
                $entry = $this->queue->callNext($doctor, $user);
                null === $entry
                    ? $this->addFlash('info', 'Nobody is waiting for this doctor.')
                    : $this->addFlash('success', \sprintf('Called token %s (%s).', $entry->getTokenNumber(), $entry->getPatientName()));
            });
        }

        return $this->redirectToRoute('staff_doctor_queue', ['id' => $doctor->getId()], 303);
    }

    #[Route('/staff/doctor/{id}/walk-in', name: 'staff_walk_in', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function walkIn(Request $request, Doctor $doctor, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($doctor, $user);
        $priority = $request->query->getBoolean('priority');

        $dto = new StaffEntryRequest();
        $form = $this->createForm(StaffEntryType::class, $dto, ['with_priority_reason' => $priority]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entry = $this->queue->addStaffEntry(
                    $doctor,
                    $user,
                    trim($dto->name),
                    $dto->normalizedPhone(),
                    $dto->channel,
                    $priority ? trim($dto->priorityReason) : null,
                );
                $this->addFlash('success', \sprintf('Token %s issued to %s.', $entry->getTokenNumber(), $entry->getPatientName()));

                return $this->redirectToRoute('staff_token_slip', ['id' => $entry->getId()], 303);
            } catch (QueueException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('staff/walk_in.html.twig', [
            'doctor' => $doctor,
            'form' => $form,
            'priority' => $priority,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/staff/entry/{id}/priority', name: 'staff_mark_priority', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function markPriority(Request $request, QueueEntry $entry, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($entry->getDoctor(), $user);

        $dto = new PriorityReasonRequest();
        $form = $this->createFormBuilder($dto)
            ->add('priorityReason', TextType::class, ['label' => 'Reason for priority (staff only)', 'empty_data' => ''])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $done = $this->attempt(fn () => $this->queue->markPriority($entry, trim($dto->priorityReason), $user));
            if ($done) {
                $this->addFlash('success', \sprintf('Token %s moved up as priority.', $entry->getTokenNumber()));
            }

            return $this->redirectToRoute('staff_doctor_queue', ['id' => $entry->getDoctor()->getId()], 303);
        }

        return $this->render('staff/mark_priority.html.twig', [
            'entry' => $entry,
            'form' => $form,
        ]);
    }

    #[Route('/staff/entry/{id}/slip', name: 'staff_token_slip', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function slip(QueueEntry $entry, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($entry->getDoctor(), $user);

        return $this->render('staff/token_slip.html.twig', [
            'entry' => $entry,
            'status' => $this->reader->patientStatus($entry),
        ]);
    }

    #[Route('/staff/entry/{id}/{action}', name: 'staff_entry_action', requirements: ['id' => '\d+', 'action' => 'call|start|complete|skip|recall|cancel|check-in'], methods: ['POST'])]
    public function entryAction(Request $request, QueueEntry $entry, string $action, #[CurrentUser] User $user): Response
    {
        $this->assertSameClinic($entry->getDoctor(), $user);

        if (\in_array($action, self::ENTRY_ACTIONS, true) && $this->isCsrfTokenValid('queue-action', (string) $request->request->get('_token'))) {
            $this->attempt(fn () => match ($action) {
                'call' => $this->queue->call($entry, $user),
                'start' => $this->queue->start($entry, $user),
                'complete' => $this->queue->complete($entry, $user),
                'skip' => $this->queue->skip($entry, $user),
                'recall' => $this->queue->recall($entry, $user),
                'cancel' => $this->queue->cancel($entry, $user),
                'check-in' => $this->queue->checkIn($entry, $user),
            });
        }

        return $this->redirectToRoute('staff_doctor_queue', ['id' => $entry->getDoctor()->getId()], 303);
    }

    private function attempt(\Closure $operation): bool
    {
        try {
            $operation();

            return true;
        } catch (QueueException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return false;
        }
    }

    private function assertSameClinic(Doctor $doctor, User $user): void
    {
        if ($doctor->getClinic()->getId() !== $user->getClinic()->getId()) {
            throw $this->createNotFoundException();
        }
    }
}

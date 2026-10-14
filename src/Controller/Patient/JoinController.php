<?php

namespace App\Controller\Patient;

use App\Dto\JoinQueueRequest;
use App\Entity\Doctor;
use App\Exception\DoctorUnavailableException;
use App\Exception\DuplicateTokenException;
use App\Form\JoinQueueType;
use App\Repository\ClinicRepository;
use App\Repository\DoctorRepository;
use App\Service\Queue\QueueDateProvider;
use App\Service\Queue\QueueReader;
use App\Service\Queue\QueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class JoinController extends AbstractController
{
    public function __construct(
        private readonly ClinicRepository $clinics,
        private readonly DoctorRepository $doctors,
        private readonly QueueReader $reader,
        private readonly QueueService $queue,
        private readonly QueueDateProvider $dates,
        #[Autowire(service: 'limiter.join_queue')] private readonly RateLimiterFactory $joinLimiter,
    ) {
    }

    #[Route('/join', name: 'join', methods: ['GET'])]
    public function chooseDoctor(Request $request): Response
    {
        $clinics = $this->clinics->findActive();
        $slug = $request->query->getString('clinic');
        $clinic = $clinics[0] ?? null;
        foreach ($clinics as $candidate) {
            if ($candidate->getSlug() === $slug) {
                $clinic = $candidate;
            }
        }

        $cards = [];
        if (null !== $clinic) {
            foreach ($this->doctors->findForClinic($clinic, true) as $doctor) {
                $cards[] = $this->reader->doctorSummary($doctor, $this->dates->today());
            }
        }

        return $this->render('patient/join_step1.html.twig', [
            'clinics' => $clinics,
            'clinic' => $clinic,
            'doctors' => $cards,
        ]);
    }

    #[Route('/join/details', name: 'join_details', methods: ['GET'])]
    public function details(Request $request): Response
    {
        $doctor = $this->doctors->find($request->query->getInt('doctor'));
        if (null === $doctor || !$this->isAcceptingTokens($doctor)) {
            $this->addFlash('error', 'That doctor is not accepting tokens right now. Please choose another.');

            return $this->redirectToRoute('join', [], 303);
        }

        $dto = new JoinQueueRequest();
        $dto->doctorId = $doctor->getId();
        $form = $this->createForm(JoinQueueType::class, $dto, ['action' => $this->generateUrl('join_submit')]);

        return $this->render('patient/join_details.html.twig', [
            'form' => $form,
            'doctor' => $doctor,
        ]);
    }

    #[Route('/join', name: 'join_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $limit = $this->joinLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Too many attempts. Please wait a few minutes and try again.');
        }

        $dto = new JoinQueueRequest();
        $form = $this->createForm(JoinQueueType::class, $dto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid() || '' !== $dto->website) {
            $doctor = $this->doctors->find((int) $dto->doctorId);

            return $this->render('patient/join_details.html.twig', [
                'form' => $form,
                'doctor' => $doctor,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        $doctor = $this->doctors->find((int) $dto->doctorId);
        if (null === $doctor || !$this->isAcceptingTokens($doctor)) {
            $this->addFlash('error', 'That doctor is not accepting tokens right now. Please choose another.');

            return $this->redirectToRoute('join', [], 303);
        }

        try {
            $entry = $this->queue->joinOnline($doctor, trim($dto->name), (string) $dto->normalizedPhone(), $dto->channel);
        } catch (DuplicateTokenException $exception) {
            $existing = $exception->getExistingEntry();
            $this->addFlash('info', \sprintf('You already have token %s with this doctor today. Here it is.', $existing->getTokenNumber()));

            return $this->redirectToRoute('patient_token', ['publicId' => $existing->getPublicId()], 303);
        } catch (DoctorUnavailableException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('join', [], 303);
        }

        return $this->redirectToRoute('patient_token', ['publicId' => $entry->getPublicId()], 303);
    }

    private function isAcceptingTokens(Doctor $doctor): bool
    {
        return $doctor->isActive() && $doctor->isAvailableToday() && $doctor->getClinic()->isActive();
    }
}

<?php

namespace App\Controller\Staff;

use App\Entity\Doctor;
use App\Entity\User;
use App\Repository\DoctorRepository;
use App\Service\Queue\QueueDateProvider;
use App\Service\Queue\QueueReader;
use App\Service\Queue\QueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DoctorRepository $doctors,
        private readonly QueueReader $reader,
        private readonly QueueService $queue,
        private readonly QueueDateProvider $dates,
    ) {
    }

    #[Route('/staff', name: 'staff_dashboard', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $cards = [];
        foreach ($this->doctors->findForClinic($user->getClinic(), true) as $doctor) {
            $cards[] = $this->reader->doctorSummary($doctor, $this->dates->today());
        }

        return $this->render('staff/dashboard.html.twig', [
            'clinic' => $user->getClinic(),
            'doctors' => $cards,
        ]);
    }

    #[Route('/staff/doctor/{id}/availability', name: 'staff_doctor_availability', methods: ['POST'])]
    public function toggleAvailability(Request $request, Doctor $doctor, #[CurrentUser] User $user): Response
    {
        if ($doctor->getClinic() !== $user->getClinic()) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('availability-'.$doctor->getId(), (string) $request->request->get('_token'))) {
            $this->queue->setDoctorAvailability($doctor, !$doctor->isAvailableToday());
            $this->addFlash('success', \sprintf('%s is now %s online tokens.', $doctor->getName(), $doctor->isAvailableToday() ? 'accepting' : 'not accepting'));
        }

        return $this->redirectToRoute('staff_dashboard', [], 303);
    }
}

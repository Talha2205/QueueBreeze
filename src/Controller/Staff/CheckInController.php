<?php

namespace App\Controller\Staff;

use App\Entity\QueueEntry;
use App\Entity\User;
use App\Exception\QueueException;
use App\Repository\QueueEntryRepository;
use App\Service\Queue\QueueDateProvider;
use App\Service\Queue\QueueReader;
use App\Service\Queue\QueueService;
use App\Service\Security\QrSigner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class CheckInController extends AbstractController
{
    public function __construct(
        private readonly QueueEntryRepository $entries,
        private readonly QueueService $queue,
        private readonly QueueReader $reader,
        private readonly QueueDateProvider $dates,
        private readonly QrSigner $qrSigner,
    ) {
    }

    #[Route('/staff/check-in', name: 'staff_check_in', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $token = trim($request->query->getString('token'));
        if ('' === $token) {
            return $this->render('staff/check_in.html.twig', ['token' => '']);
        }

        $entry = $this->entries->findByTokenOnDate($user->getClinic(), $this->dates->today(), $token);
        if (null === $entry) {
            $this->addFlash('error', \sprintf('No token %s was issued today.', strtoupper($token)));

            return $this->render('staff/check_in.html.twig', ['token' => $token]);
        }

        return $this->checkIn($entry, $user);
    }

    #[Route('/staff/check-in/scan', name: 'staff_check_in_scan', methods: ['GET'])]
    public function scan(Request $request, #[CurrentUser] User $user): Response
    {
        $entry = $this->entries->findOneBy(['publicId' => $request->query->getString('token')]);

        if (null === $entry
            || $entry->getClinic()->getId() !== $user->getClinic()->getId()
            || !$this->qrSigner->isValid($entry->getPublicId(), $entry->getClinic()->getId(), $entry->getQueueDate(), $request->query->getString('sig'))
        ) {
            $this->addFlash('error', 'That QR code is not valid for this clinic.');

            return $this->redirectToRoute('staff_check_in');
        }

        if ($entry->getQueueDate() != $this->dates->today()) {
            $this->addFlash('error', \sprintf('Token %s was issued for %s, not today.', $entry->getTokenNumber(), $entry->getQueueDate()->format('d M Y')));

            return $this->redirectToRoute('staff_check_in');
        }

        return $this->checkIn($entry, $user);
    }

    private function checkIn(QueueEntry $entry, User $user): Response
    {
        $alreadyCheckedIn = $entry->isCheckedIn();

        try {
            $this->queue->checkIn($entry, $user);
        } catch (QueueException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->render('staff/check_in_result.html.twig', [
            'entry' => $entry,
            'status' => $this->reader->patientStatus($entry),
            'alreadyCheckedIn' => $alreadyCheckedIn,
        ]);
    }
}

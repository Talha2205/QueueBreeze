<?php

namespace App\Controller\Patient;

use App\Entity\QueueEntry;
use App\Exception\InvalidQueueTransitionException;
use App\Repository\QueueEntryRepository;
use App\Service\Queue\QueueReader;
use App\Service\Queue\QueueService;
use App\Service\Realtime\QueueBroadcaster;
use App\Service\Security\QrSigner;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/q/{publicId}', requirements: ['publicId' => '[a-f0-9]{40}'])]
final class TokenController extends AbstractController
{
    public function __construct(
        private readonly QueueEntryRepository $entries,
        private readonly QueueReader $reader,
        private readonly QueueService $queue,
        private readonly QueueBroadcaster $broadcaster,
        private readonly QrSigner $qrSigner,
        #[Autowire('%app.mercure_public_url%')] private readonly string $mercurePublicUrl,
    ) {
    }

    #[Route('', name: 'patient_token', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $entry = $this->findEntry($publicId);

        return $this->render('patient/token.html.twig', [
            'entry' => $entry,
            'status' => $this->reader->patientStatus($entry),
            'hubUrl' => $this->mercurePublicUrl,
            'topic' => QueueBroadcaster::patientTopic($entry->getPublicId()),
        ]);
    }

    #[Route('/live', name: 'patient_token_live', methods: ['GET'])]
    public function live(string $publicId): Response
    {
        $entry = $this->findEntry($publicId);

        return new Response($this->broadcaster->renderPatient($entry), headers: ['Content-Type' => 'text/vnd.turbo-stream.html']);
    }

    #[Route('/qr.png', name: 'patient_token_qr', methods: ['GET'])]
    public function qr(string $publicId): Response
    {
        $entry = $this->findEntry($publicId);
        $result = (new Builder(writer: new PngWriter(), data: $this->qrSigner->checkInUrl($entry), size: 300, margin: 10))->build();

        return new Response($result->getString(), headers: [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('/cancel', name: 'patient_token_cancel', methods: ['POST'])]
    public function cancel(Request $request, string $publicId): Response
    {
        $entry = $this->findEntry($publicId);

        if (!$this->isCsrfTokenValid('cancel-token', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');

            return $this->redirectToRoute('patient_token', ['publicId' => $publicId], 303);
        }

        try {
            $this->queue->cancel($entry, null);
            $this->addFlash('info', 'Your token has been cancelled.');
        } catch (InvalidQueueTransitionException) {
            $this->addFlash('error', 'This token can no longer be cancelled.');
        }

        return $this->redirectToRoute('patient_token', ['publicId' => $publicId], 303);
    }

    private function findEntry(string $publicId): QueueEntry
    {
        $entry = $this->entries->findOneBy(['publicId' => $publicId]);
        if (null === $entry) {
            throw $this->createNotFoundException('Token not found.');
        }

        return $entry;
    }
}

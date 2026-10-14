<?php

namespace App\Controller\Staff;

use App\Entity\Doctor;
use App\Entity\User;
use App\Form\ClinicSettingsType;
use App\Form\DoctorType;
use App\Form\ReceptionistType;
use App\Repository\DoctorRepository;
use App\Repository\QueueEntryRepository;
use App\Repository\UserRepository;
use App\Service\Queue\DailyReportBuilder;
use App\Service\Queue\QueueDateProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/staff/admin')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DoctorRepository $doctors,
        private readonly UserRepository $users,
    ) {
    }

    #[Route('/doctors', name: 'admin_doctors', methods: ['GET'])]
    public function doctors(#[CurrentUser] User $user): Response
    {
        return $this->render('staff/admin/doctors.html.twig', [
            'doctors' => $this->doctors->findForClinic($user->getClinic()),
        ]);
    }

    #[Route('/doctors/new', name: 'admin_doctor_new', methods: ['GET', 'POST'])]
    #[Route('/doctors/{id}/edit', name: 'admin_doctor_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editDoctor(Request $request, #[CurrentUser] User $user, ?Doctor $doctor = null): Response
    {
        if (null !== $doctor && $doctor->getClinic()->getId() !== $user->getClinic()->getId()) {
            throw $this->createNotFoundException();
        }

        $isNew = null === $doctor;
        $doctor ??= new Doctor($user->getClinic(), '', 'A');
        $form = $this->createForm(DoctorType::class, $doctor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clash = $this->doctors->findOneByClinicAndPrefix($user->getClinic(), $doctor->getTokenPrefix());
            if (null !== $clash && $clash !== $doctor) {
                $form->get('tokenPrefix')->addError(new FormError(\sprintf('Prefix %s is already used by %s.', $doctor->getTokenPrefix(), $clash->getName())));
            } else {
                $this->em->persist($doctor);
                $this->em->flush();
                $this->addFlash('success', \sprintf('%s saved.', $doctor->getName()));

                return $this->redirectToRoute('admin_doctors', [], 303);
            }
        }

        return $this->renderForm('staff/admin/doctor_form.html.twig', $form, ['doctor' => $doctor, 'isNew' => $isNew]);
    }

    #[Route('/staff', name: 'admin_staff', methods: ['GET'])]
    public function staff(#[CurrentUser] User $user): Response
    {
        return $this->render('staff/admin/staff.html.twig', [
            'users' => $this->users->findForClinic($user->getClinic()),
        ]);
    }

    #[Route('/staff/new', name: 'admin_staff_new', methods: ['GET', 'POST'])]
    #[Route('/staff/{id}/edit', name: 'admin_staff_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editStaff(Request $request, UserPasswordHasherInterface $hasher, #[CurrentUser] User $user, ?User $member = null): Response
    {
        if (null !== $member && $member->getClinic()->getId() !== $user->getClinic()->getId()) {
            throw $this->createNotFoundException();
        }

        $isNew = null === $member;
        $member ??= new User($user->getClinic(), '', '', User::ROLE_RECEPTIONIST);
        $form = $this->createForm(ReceptionistType::class, $member, [
            'is_admin' => $member->isAdmin(),
            'password_required' => $isNew,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isSelf = $member === $user;
            $makeAdmin = (bool) $form->get('isAdmin')->getData();

            if ($isSelf && (!$makeAdmin || !$member->isActive())) {
                $this->addFlash('error', 'You cannot remove your own admin access or deactivate yourself.');
            } else {
                $member->setAdmin($makeAdmin);
                $plain = (string) $form->get('plainPassword')->getData();
                if ('' !== $plain) {
                    $member->setPassword($hasher->hashPassword($member, $plain));
                }
                $this->em->persist($member);
                $this->em->flush();
                $this->addFlash('success', \sprintf('%s saved.', $member->getName()));

                return $this->redirectToRoute('admin_staff', [], 303);
            }
        }

        return $this->renderForm('staff/admin/staff_form.html.twig', $form, ['member' => $member, 'isNew' => $isNew]);
    }

    #[Route('/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, #[CurrentUser] User $user): Response
    {
        $form = $this->createForm(ClinicSettingsType::class, $user->getClinic());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Clinic settings saved.');

            return $this->redirectToRoute('admin_settings', [], 303);
        }

        return $this->renderForm('staff/admin/settings.html.twig', $form);
    }

    #[Route('/reports', name: 'admin_reports', methods: ['GET'])]
    public function reports(Request $request, QueueEntryRepository $entries, DailyReportBuilder $reports, QueueDateProvider $dates, #[CurrentUser] User $user): Response
    {
        $date = $dates->today();
        $requested = $request->query->getString('date');
        if ('' !== $requested && false !== $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $requested, $date->getTimezone())) {
            $date = $parsed;
        }

        $all = $entries->findForClinicOnDate($user->getClinic(), $date);
        $byDoctor = [];
        foreach ($reports->groupByDoctor($all) as $group) {
            $byDoctor[] = ['doctor' => $group[0]->getDoctor(), 'summary' => $reports->summarize($group)];
        }

        return $this->render('staff/admin/reports.html.twig', [
            'date' => $date,
            'summary' => $reports->summarize($all),
            'byDoctor' => $byDoctor,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function renderForm(string $template, FormInterface $form, array $context = []): Response
    {
        $status = $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render($template, ['form' => $form] + $context, new Response(status: $status));
    }
}

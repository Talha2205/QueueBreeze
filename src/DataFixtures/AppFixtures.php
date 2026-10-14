<?php

namespace App\DataFixtures;

use App\Entity\Clinic;
use App\Entity\Doctor;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Service\Queue\QueueService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const ADMIN_EMAIL = 'admin@queuebreeze.test';
    public const ADMIN_PASSWORD = 'Admin@12345';
    public const RECEPTIONIST_EMAIL = 'reception@queuebreeze.test';
    public const RECEPTIONIST_PASSWORD = 'Reception@123';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly QueueService $queue,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $clinic = new Clinic('City Care Clinic', 'city-care');
        $clinic->setAddress('12 Main Boulevard, Gulberg, Lahore');
        $clinic->setPhone('+92 42 3500 0000');
        $manager->persist($clinic);

        $general = $this->doctor($manager, $clinic, 'Dr. Ayesha Khan', 'General Physician', 'A', 10);
        $children = $this->doctor($manager, $clinic, 'Dr. Bilal Ahmed', 'Pediatrics', 'B', 15);
        $skin = $this->doctor($manager, $clinic, 'Dr. Sana Malik', 'Dermatology', 'C', 12);
        $skin->setIsAvailableToday(false);

        $admin = $this->user($manager, $clinic, 'Clinic Admin', self::ADMIN_EMAIL, self::ADMIN_PASSWORD, User::ROLE_ADMIN);
        $this->user($manager, $clinic, 'Front Desk', self::RECEPTIONIST_EMAIL, self::RECEPTIONIST_PASSWORD, User::ROLE_RECEPTIONIST);

        $manager->flush();

        // A realistic morning: a few online tokens, a walk-in, and one patient already with the doctor.
        $this->queue->joinOnline($general, 'Usman Tariq', '+923001234501', NotificationChannel::WHATSAPP);
        $this->queue->joinOnline($general, 'Hina Javed', '+923001234502', NotificationChannel::SMS);
        $this->queue->addStaffEntry($general, $admin, 'Rashid Ali', null, null, null);
        $this->queue->joinOnline($general, 'Fatima Noor', '+923001234503', NotificationChannel::WHATSAPP);
        $this->queue->joinOnline($children, 'Ahmed Raza (child)', '+923001234504', NotificationChannel::SMS);

        $first = $this->queue->callNext($general, $admin);
        $this->queue->start($first, $admin);
    }

    private function doctor(ObjectManager $manager, Clinic $clinic, string $name, string $specialty, string $prefix, int $minutes): Doctor
    {
        $doctor = new Doctor($clinic, $name, $prefix);
        $doctor->setSpecialty($specialty);
        $doctor->setAvgConsultationMinutes($minutes);
        $manager->persist($doctor);

        return $doctor;
    }

    private function user(ObjectManager $manager, Clinic $clinic, string $name, string $email, string $password, string $role): User
    {
        $user = new User($clinic, $name, $email, $role);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $manager->persist($user);

        return $user;
    }
}

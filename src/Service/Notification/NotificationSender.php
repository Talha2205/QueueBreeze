<?php

namespace App\Service\Notification;

use App\Enum\NotificationChannel;
use App\Enum\NotificationProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

final class NotificationSender
{
    public function __construct(
        private readonly TexterInterface $texter,
        #[Autowire('%env(TWILIO_WHATSAPP_FROM)%')] private readonly string $whatsappFrom,
    ) {
    }

    public function send(string $phone, NotificationChannel $channel, NotificationProvider $provider, string $text): string
    {
        $isWhatsApp = NotificationChannel::WHATSAPP === $channel;

        $message = new SmsMessage(
            $isWhatsApp ? 'whatsapp:'.$phone : $phone,
            $text,
            $isWhatsApp ? $this->whatsappFrom : '',
        );
        $message->transport($provider->value);

        $sent = $this->texter->send($message);

        return $sent?->getMessageId() ?? 'accepted';
    }
}

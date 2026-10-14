<?php

namespace App\Notifier;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class LogTransport implements TransportInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __toString(): string
    {
        return 'log://default';
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    public function send(MessageInterface $message): ?SentMessage
    {
        $id = bin2hex(random_bytes(8));

        $this->logger->info('Patient notification written to log (nothing was sent).', [
            'id' => $id,
            'recipient' => $message instanceof SmsMessage ? $this->mask($message->getPhone()) : null,
            'body' => $message->getSubject(),
        ]);

        $sent = new SentMessage($message, 'log');
        $sent->setMessageId($id);

        return $sent;
    }

    private function mask(string $phone): string
    {
        return substr($phone, 0, 4).str_repeat('*', max(0, \strlen($phone) - 7)).substr($phone, -3);
    }
}

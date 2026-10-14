<?php

namespace App\Notifier;

use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;

final class LogTransportFactory implements TransportFactoryInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'log', ['log']);
        }

        return new LogTransport($this->logger);
    }

    public function supports(Dsn $dsn): bool
    {
        return 'log' === $dsn->getScheme();
    }
}

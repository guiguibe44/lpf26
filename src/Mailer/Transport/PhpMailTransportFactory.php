<?php

declare(strict_types=1);

namespace App\Mailer\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;

final class PhpMailTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!\in_array($dsn->getScheme(), $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'php-mail', $this->getSupportedSchemes());
        }

        return new PhpMailTransport($this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['php-mail'];
    }
}

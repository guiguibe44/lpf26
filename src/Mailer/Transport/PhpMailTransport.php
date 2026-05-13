<?php

declare(strict_types=1);

namespace App\Mailer\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Envoie via la fonction PHP {@see mail()} (configuration php.ini / hébergeur).
 * Utile sur mutualisé OVH quand sendmail en mode -bs échoue mais mail() fonctionne.
 */
final class PhpMailTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (!$original instanceof Message) {
            throw new TransportException(sprintf(
                'PhpMailTransport : message de type %s non pris en charge (attendu %s).',
                get_debug_type($original),
                Message::class
            ));
        }

        $email = MessageConverter::toEmail($original);

        $recipients = $message->getEnvelope()->getRecipients();
        if ([] === $recipients) {
            throw new TransportException('PhpMailTransport : aucun destinataire.');
        }

        $to = implode(', ', array_map(
            static fn (Address $a) => $a->getEncodedAddress(),
            $recipients
        ));

        $subject = $email->getSubject() ?? '';
        if (!preg_match('/^[\x20-\x7E]*$/', $subject)) {
            $subject = mb_encode_mimeheader($subject, 'UTF-8', true);
        }

        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        if (\is_resource($html)) {
            $html = stream_get_contents($html) ?: '';
        }
        if (\is_resource($text)) {
            $text = stream_get_contents($text) ?: '';
        }

        $body = null !== $html && '' !== $html ? (string) $html : (string) $text;
        if ('' === $body) {
            throw new TransportException('PhpMailTransport : corps du message vide (pas de partie texte ni HTML).');
        }

        $headers = [
            'MIME-Version: 1.0',
        ];

        if (null !== $html && '' !== $html) {
            $charset = $email->getHtmlCharset() ?? 'UTF-8';
            $headers[] = 'Content-Type: text/html; charset='.$charset;
        } else {
            $charset = $email->getTextCharset() ?? 'UTF-8';
            $headers[] = 'Content-Type: text/plain; charset='.$charset;
        }

        $sender = $message->getEnvelope()->getSender();
        $headers[] = 'From: '.$sender->toString();

        $replyTos = $email->getReplyTo();
        if ([] !== $replyTos) {
            $headers[] = 'Reply-To: '.$replyTos[0]->toString();
        }

        $ccList = $email->getCc();
        if ([] !== $ccList) {
            $headers[] = 'Cc: '.implode(', ', array_map(
                static fn (Address $a) => $a->toString(),
                $ccList
            ));
        }

        $bccList = $email->getBcc();
        if ([] !== $bccList) {
            $headers[] = 'Bcc: '.implode(', ', array_map(
                static fn (Address $a) => $a->toString(),
                $bccList
            ));
        }

        $headerString = implode("\r\n", $headers);

        $ok = @mail($to, $subject, $body, $headerString);
        if (!$ok) {
            throw new TransportException('La fonction PHP mail() a retourné false (voir la config sendmail_path / hébergeur).');
        }
    }

    public function __toString(): string
    {
        return 'php-mail://default';
    }
}

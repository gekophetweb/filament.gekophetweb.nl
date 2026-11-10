<?php

namespace App\Support\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps a mail transport and adds a DKIM signature to every message just before it is sent.
 */
final class DkimSigningTransport implements TransportInterface
{
    public function __construct(
        private TransportInterface $transport,
        private DkimSigner $signer,
    ) {}

    /**
     * Build the signer from a mailer's "dkim" config, or return the transport unchanged when DKIM is not set up.
     *
     * @param  array{domain?: ?string, selector?: ?string, private_key?: ?string}  $dkim
     */
    public static function wrap(TransportInterface $transport, array $dkim): TransportInterface
    {
        $privateKey = DkimKey::decode($dkim['private_key'] ?? null);

        if (blank($dkim['domain'] ?? null) || blank($dkim['selector'] ?? null) || $privateKey === null) {
            return $transport;
        }

        return new self($transport, new DkimSigner($privateKey, $dkim['domain'], $dkim['selector']));
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Message) {
            // The envelope comes from the unsigned message, as the inner transport would make it.
            $envelope ??= Envelope::create($message);
            $message = $this->signer->sign($message);
        }

        return $this->transport->send($message, $envelope);
    }

    public function __toString(): string
    {
        return (string) $this->transport;
    }
}

<?php

namespace App\Domain\StatusPage\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The status page's mails, worded as monitoreo's (the "ya funciona" of D-063 and the domain
 * notices of D-040): a subject and a small HTML body, on the `mail` queue. Callers check
 * `newdesk.monitoring.notify` first (CA-13.20).
 */
final class StatusPageMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $mailSubject, public readonly string $body)
    {
        $this->onQueue('mail');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventMail extends Mailable
{
    use Queueable, SerializesModels;

    private array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Research Mail',

        );
    }

    /**
 * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.research',
            with: [
                'start' => $this->payload['start'],
                'end' => $this->payload['end'],
                'joining_from_usergems' => $this->payload['joining_from_usergems'],
                'people' => $this->payload['people'],
                'companies' => $this->payload['companies'],
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

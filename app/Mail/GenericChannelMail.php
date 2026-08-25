<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericChannelMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Notificación institucional'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.generic-channel',
        );
    }
}

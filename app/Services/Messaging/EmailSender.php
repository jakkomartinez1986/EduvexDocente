<?php

namespace App\Services\Messaging;

use App\Mail\GenericChannelMail;
use App\Services\Messaging\Contracts\ChannelSender;
use Illuminate\Support\Facades\Mail;

class EmailSender implements ChannelSender
{
    public function send(string $to, string $message, ?string $pdfPath = null, ?string $pdfName = null): SendResult
    {
        try {
            $mailable = new GenericChannelMail($message);

            if ($pdfPath !== null && is_file($pdfPath)) {
                $mailable->attach($pdfPath, ['as' => $pdfName ?? basename($pdfPath)]);
            }

            Mail::to($to)->send($mailable);

            return SendResult::ok();
        } catch (\Throwable $e) {
            return SendResult::fail($e->getMessage());
        }
    }

    public function verify(): SendResult
    {
        return config('mail.default') !== null ? SendResult::ok() : SendResult::fail('MAIL_MAILER no configurado.');
    }
}

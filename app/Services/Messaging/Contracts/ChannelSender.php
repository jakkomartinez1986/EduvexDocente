<?php

namespace App\Services\Messaging\Contracts;

use App\Services\Messaging\SendResult;

interface ChannelSender
{
    public function send(string $to, string $message, ?string $pdfPath = null, ?string $pdfName = null): SendResult;

    public function verify(): SendResult;
}

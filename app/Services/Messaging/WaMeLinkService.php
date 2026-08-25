<?php

namespace App\Services\Messaging;

/**
 * Builds manual WhatsApp links (wa.me). Text only: never attaches files.
 * The message text must be produced by the existing generators and passed in,
 * so the link always contains exactly the same text as any other channel.
 */
class WaMeLinkService
{
    /**
     * Normalize any stored phone to international digits for wa.me
     * (Ecuador default country code 593), e.g. 0991234567 -> 593991234567.
     */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '593')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '593'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '593'.$digits;
        }

        return $digits;
    }

    /**
     * https://wa.me/{normalized phone}?text={raw url encoded message}
     */
    public function buildLink(string $phone, string $message): string
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === null) {
            return '';
        }

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($message);
    }

    /**
     * One individual link per recipient, same message for all of them.
     * Recipients may be plain phone strings or arrays with a "phone" key.
     *
     * @param  array<int, mixed>  $recipients
     * @return array<int, string>
     */
    public function buildLinks(array $recipients, string $message): array
    {
        $links = [];

        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $phone = isset($recipient['phone']) ? (string) $recipient['phone'] : '';
            } else {
                $phone = (string) $recipient;
            }

            $link = $this->buildLink($phone, $message);

            if ($link !== '') {
                $links[] = $link;
            }
        }

        return $links;
    }
}

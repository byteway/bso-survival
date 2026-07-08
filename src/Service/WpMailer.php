<?php

namespace BSO\Survival\Service;

use BSO\Survival\Contracts\MailerInterface;

class WpMailer implements MailerInterface {
    /**
     * @param array<int, string>|string $headers
     * @param array<int, string> $attachments
     */
    public function send(string $recipient, string $subject, string $body, $headers = [], array $attachments = []): bool {
        if (!function_exists('wp_mail')) {
            return false;
        }

        return (bool) wp_mail($recipient, $subject, $body, $headers, $attachments);
    }
}

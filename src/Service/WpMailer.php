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

        $recipient = strtolower(trim($recipient));
        $subject = trim($subject);
        $body = trim($body);

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($subject === '' || $body === '') {
            return false;
        }

        try {
            return (bool) wp_mail($recipient, $subject, $body, $headers, $attachments);
        } catch (\Throwable $exception) {
            return false;
        }
    }
}

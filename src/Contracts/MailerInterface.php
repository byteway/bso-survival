<?php

namespace BSO\Survival\Contracts;

interface MailerInterface {
    /**
     * @param array<int, string>|string $headers
     * @param array<int, string> $attachments
     */
    public function send(string $recipient, string $subject, string $body, $headers = [], array $attachments = []): bool;
}

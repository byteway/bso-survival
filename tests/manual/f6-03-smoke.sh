#!/usr/bin/env bash
set -euo pipefail

if ! command -v wp >/dev/null 2>&1; then
    echo "ERROR: wp-cli niet gevonden in PATH." >&2
    exit 1
fi

EVENT_ID="${1:-${BSO_EVENT_ID:-0}}"
if [[ "$EVENT_ID" -le 0 ]]; then
    echo "Gebruik: $0 <event_id>" >&2
    echo "Of zet env var BSO_EVENT_ID=<event_id>." >&2
    exit 1
fi

RUN_ID="smoke-$(date -u +%Y%m%d%H%M%S)"
PHP_FILE="$(mktemp)"
trap 'rm -f "$PHP_FILE"' EXIT

cat > "$PHP_FILE" <<'PHP'
<?php

use BSO\Survival\Database\Repository\DashboardMessageRepository;
use BSO\Survival\Service\DashboardMessageService;

$eventId = (int) getenv('BSO_EVENT_ID');
$runId = (string) getenv('BSO_RUN_ID');

if ($eventId <= 0) {
    fwrite(STDERR, "ERROR: BSO_EVENT_ID ontbreekt of is ongeldig.\n");
    exit(1);
}

$service = new DashboardMessageService(new DashboardMessageRepository());
$createdIds = [];
$errors = [];

$assert = static function (bool $condition, string $message) use (&$errors): void {
    if ($condition) {
        fwrite(STDOUT, "PASS: {$message}\n");
        return;
    }

    $errors[] = $message;
    fwrite(STDOUT, "FAIL: {$message}\n");
};

$createMessage = static function (string $label, array $extraPayload = []) use ($service, $eventId, $runId, &$createdIds): object {
    $payload = array_merge([
        'event_id' => $eventId,
        'type' => 'info',
        'text' => sprintf('[F6-03-SMOKE %s] %s', $runId, $label),
        'scope' => 'event',
        'status' => 'actief',
        'visibility' => 'intern',
        'changed_by' => 'smoke-f6-03',
    ], $extraPayload);

    $row = $service->create($payload);
    $createdIds[] = (int) ($row->id ?? 0);
    return $row;
};

$isVisible = static function (array $rows, string $label, string $runId): bool {
    $needle = sprintf('[F6-03-SMOKE %s] %s', $runId, $label);
    foreach ($rows as $row) {
        $text = (string) ($row->text ?? '');
        if (strpos($text, $needle) !== false) {
            return true;
        }
    }

    return false;
};

try {
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $fmt = static function (DateTimeImmutable $date): string {
        return $date->format('Y-m-d H:i:s');
    };

    $createMessage('future-window', [
        'visible_from' => $fmt($now->modify('+5 minutes')),
        'visible_until' => $fmt($now->modify('+20 minutes')),
    ]);

    $createMessage('expired-window', [
        'visible_from' => $fmt($now->modify('-20 minutes')),
        'visible_until' => $fmt($now->modify('-5 minutes')),
    ]);

    $createMessage('current-window', [
        'visible_from' => $fmt($now),
        'visible_until' => $fmt($now->modify('+3 minutes')),
    ]);

    $invalidRejected = false;
    try {
        $createMessage('invalid-window', [
            'visible_from' => $fmt($now->modify('+10 minutes')),
            'visible_until' => $fmt($now->modify('+1 minute')),
        ]);
    } catch (\InvalidArgumentException $exception) {
        $invalidRejected = true;
    }

    $activeRows = $service->listActiveForEvent($eventId, 200, 'all');

    $assert(!$isVisible($activeRows, 'future-window', $runId), 'future visible_from is nog niet zichtbaar');
    $assert(!$isVisible($activeRows, 'expired-window', $runId), 'verlopen visible_until is niet zichtbaar');
    $assert($isVisible($activeRows, 'current-window', $runId), 'huidig zichtvenster is zichtbaar');
    $assert($invalidRejected, 'ongeldige tijdcombinatie wordt geweigerd');
} catch (\Throwable $exception) {
    $errors[] = 'Onverwachte fout: ' . $exception->getMessage();
    fwrite(STDERR, "ERROR: {$exception->getMessage()}\n");
} finally {
    foreach ($createdIds as $messageId) {
        if ($messageId <= 0) {
            continue;
        }

        try {
            $service->delete($messageId, $eventId, 'smoke-f6-03');
        } catch (\Throwable $cleanupException) {
            fwrite(STDOUT, "WARN: cleanup mislukt voor message_id={$messageId}: {$cleanupException->getMessage()}\n");
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "\nF6-03 SMOKE RESULT: FAIL (" . count($errors) . " issue(s))\n");
    foreach ($errors as $entry) {
        fwrite(STDERR, "- {$entry}\n");
    }
    exit(1);
}

fwrite(STDOUT, "\nF6-03 SMOKE RESULT: PASS\n");
exit(0);
PHP

echo "Running F6-03 smoke checks voor event_id=$EVENT_ID (run_id=$RUN_ID)"
if [[ -n "${WORDPRESS_PATH:-}" ]]; then
    BSO_EVENT_ID="$EVENT_ID" BSO_RUN_ID="$RUN_ID" wp --path="$WORDPRESS_PATH" eval-file "$PHP_FILE"
else
    BSO_EVENT_ID="$EVENT_ID" BSO_RUN_ID="$RUN_ID" wp eval-file "$PHP_FILE"
fi

<?php

$codes = [
    '[bso_survival_dashboard]',
    '[bso_survival_parts event_id="2"]',
    '[bso_survival_teams event_id="2"]',
    '[bso_survival_event_overview event_id="2"]',
    '[bso_survival_event_overview event_id="2" compact="yes"]',
    '[bso_survival_event_summary event_id="2"]',
];

$failed = 0;

foreach ($codes as $code) {
    $html = (string) do_shortcode($code);
    $trimmed = trim($html);
    $isFailText = (
        strpos($trimmed, 'niet beschikbaar') !== false
        || strpos($trimmed, 'Geen onderdelen gevonden') !== false
        || strpos($trimmed, 'Geen teams gevonden') !== false
    );
    $pass = ($trimmed !== '' && !$isFailText);

    echo ($pass ? 'PASS' : 'FAIL') . ' :: ' . $code . ' :: len=' . strlen($trimmed) . PHP_EOL;
    if (!$pass) {
        $failed++;
    }
}

if ($failed > 0) {
    fwrite(STDERR, 'Shortcode smoke failed with ' . $failed . ' failure(s).' . PHP_EOL);
    exit(1);
}

echo 'Shortcode smoke passed.' . PHP_EOL;

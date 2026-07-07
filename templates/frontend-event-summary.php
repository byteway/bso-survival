<?php
/**
 * Frontend event summary template for shortcode output.
 *
 * @var string $title
 * @var array<string, mixed> $overview
 */
?>
<section class="bso-survival-summary">
    <header class="bso-survival-summary__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-summary__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $overview['event']->id, (string) $overview['event']->name)); ?>
        </p>
    </header>

    <div class="bso-survival-summary__stats">
        <p><?php echo esc_html(sprintf('Status: %s', (string) ($overview['status']['event_status'] ?? 'onbekend'))); ?></p>
        <p><?php echo esc_html(sprintf('Onderdelen: %d', (int) $overview['counts']['parts'])); ?></p>
        <p><?php echo esc_html(sprintf('Teams: %d', (int) $overview['counts']['teams'])); ?></p>
        <p><?php echo esc_html(sprintf('Klaar voor planning: %s', $overview['status']['is_ready_for_planning'] ? 'ja' : 'nee')); ?></p>
    </div>
</section>

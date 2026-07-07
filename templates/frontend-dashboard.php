<?php
/**
 * Frontend dashboard template for shortcode output.
 *
 * @var string $title
 * @var array<string, mixed> $overview
 */
?>
<section class="bso-survival-dashboard">
    <header class="bso-survival-dashboard__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-dashboard__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $overview['event']->id, (string) $overview['event']->name)); ?>
        </p>
    </header>
    <div class="bso-survival-dashboard__content">
        <div class="bso-survival-dashboard__summary">
            <p><?php echo esc_html(sprintf('Status: %s', (string) ($overview['status']['event_status'] ?? 'onbekend'))); ?></p>
            <p><?php echo esc_html(sprintf('Onderdelen: %d', (int) $overview['counts']['parts'])); ?></p>
            <p><?php echo esc_html(sprintf('Teams: %d', (int) $overview['counts']['teams'])); ?></p>
            <p><?php echo esc_html(sprintf('Klaar voor planning: %s', $overview['status']['is_ready_for_planning'] ? 'ja' : 'nee')); ?></p>
        </div>

        <div class="bso-survival-dashboard__lists">
            <div class="bso-survival-dashboard__list">
                <h3><?php esc_html_e('Onderdelen', 'bso-survival'); ?></h3>
                <ul>
                    <?php foreach ($overview['parts'] as $part) : ?>
                        <li><?php echo esc_html($part->name); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="bso-survival-dashboard__list">
                <h3><?php esc_html_e('Teams', 'bso-survival'); ?></h3>
                <ul>
                    <?php foreach ($overview['teams'] as $team) : ?>
                        <li><?php echo esc_html($team->name); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php
/**
 * Frontend dashboard template for shortcode output.
 *
 * @var string $title
 * @var array<string, mixed> $overview
 * @var string $widgetsHtml
 * @var string $operationsWidgetsHtml
 */
?>
<section class="bso-survival-dashboard">
    <header class="bso-survival-dashboard__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-dashboard__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $overview['event']->id, (string) $overview['event']->name)); ?>
        </p>
    </header>
    <?php if (!empty($overview['status']['is_read_only'])) : ?>
        <div class="bso-survival-status-notice bso-survival-status-notice--readonly">
            <p><?php echo esc_html(__('Dit event is read-only afgesloten. Operationele widgets zijn verborgen.', 'bso-survival')); ?></p>
        </div>
    <?php endif; ?>
    <?php if (!empty($overview['status']['is_published'])) : ?>
        <div class="bso-survival-status-notice bso-survival-status-notice--published">
            <p><?php echo esc_html(__('De eindstand van dit event is gepubliceerd.', 'bso-survival')); ?></p>
        </div>
    <?php endif; ?>
    <div class="bso-survival-dashboard__content">
        <?php if ($widgetsHtml !== '') : ?>
            <div class="bso-survival-dashboard__widgets">
                <?php echo $widgetsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <?php if ($operationsWidgetsHtml !== '') : ?>
            <div class="bso-survival-dashboard__operations">
                <?php echo $operationsWidgetsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <div class="bso-survival-dashboard__summary">
            <p><?php echo esc_html(sprintf('Status: %s', (string) ($overview['status']['event_status'] ?? 'onbekend'))); ?></p>
            <p><?php echo esc_html(sprintf('Onderdelen: %d', (int) $overview['counts']['parts'])); ?></p>
            <p><?php echo esc_html(sprintf('Teams: %d', (int) $overview['counts']['teams'])); ?></p>
            <p><?php echo esc_html(sprintf('Inschrijving: %d / %s', (int) ($overview['counts']['registered_teams'] ?? 0), (int) ($overview['counts']['max_teams'] ?? 0) > 0 ? (string) (int) $overview['counts']['max_teams'] : '?')); ?></p>
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

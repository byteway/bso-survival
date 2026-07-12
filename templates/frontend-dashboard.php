<?php
/**
 * Frontend dashboard template for shortcode output.
 *
 * @var string $title
 * @var array<string, mixed> $overview
 * @var string $widgetsHtml
 * @var string $operationsWidgetsHtml
 * @var array<int, object> $eventOptions
 * @var int $selectedEventId
 */

$eventStatus = (string) ($overview['status']['event_status'] ?? 'onbekend');
$eventOptions = is_array($eventOptions ?? null) ? $eventOptions : [];
$selectedEventId = isset($selectedEventId) ? (int) $selectedEventId : (int) ($overview['event']->id ?? 0);
$pageId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
$registeredTeams = (int) ($overview['counts']['registered_teams'] ?? 0);
$maxTeams = (int) ($overview['counts']['max_teams'] ?? 0);
$registrationLabel = $maxTeams > 0
    ? sprintf('%d / %d', $registeredTeams, $maxTeams)
    : sprintf('%d / ?', $registeredTeams);
$planningLabel = !empty($overview['status']['is_ready_for_planning'])
    ? __('Ja', 'bso-survival')
    : __('Nee', 'bso-survival');
?>
<section class="bso-survival-dashboard">
    <header class="bso-survival-dashboard__header">
        <p class="bso-survival-dashboard__eyebrow"><?php esc_html_e('Team Dashboard', 'bso-survival'); ?></p>
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($eventOptions !== []) : ?>
            <form class="bso-survival-dashboard__event-filter" method="get">
                <?php if ($pageId > 0) : ?>
                    <input type="hidden" name="page_id" value="<?php echo $pageId; ?>" />
                <?php endif; ?>
                <label for="bso-dashboard-event-id"><?php esc_html_e('Event', 'bso-survival'); ?></label>
                <select id="bso-dashboard-event-id" name="event_id" onchange="this.form.submit()">
                    <?php foreach ($eventOptions as $eventOption) : ?>
                        <?php
                        $optionId = (int) ($eventOption->id ?? 0);
                        $optionName = (string) ($eventOption->name ?? '');
                        $optionDate = (string) ($eventOption->event_date ?? '');
                        ?>
                        <option value="<?php echo $optionId; ?>"<?php echo $optionId === $selectedEventId ? ' selected="selected"' : ''; ?>>
                            <?php echo esc_html(sprintf('#%d - %s (%s)', $optionId, $optionName, $optionDate)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Toon event', 'bso-survival'); ?></button>
                </noscript>
            </form>
        <?php endif; ?>
        <p class="bso-survival-dashboard__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $overview['event']->id, (string) $overview['event']->name)); ?>
        </p>
        <p class="bso-survival-dashboard__breadcrumb"><?php esc_html_e('Home / Team Dashboard', 'bso-survival'); ?></p>
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
        <div class="bso-survival-dashboard__kpis">
            <article class="bso-survival-dashboard__kpi-card">
                <strong><?php echo esc_html($eventStatus); ?></strong>
                <span><?php esc_html_e('Status', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong><?php echo esc_html((string) (int) $overview['counts']['parts']); ?></strong>
                <span><?php esc_html_e('Onderdelen', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong><?php echo esc_html((string) (int) $overview['counts']['teams']); ?></strong>
                <span><?php esc_html_e('Teams', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong><?php echo esc_html($registrationLabel); ?></strong>
                <span><?php esc_html_e('Inschrijving', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong><?php echo esc_html($planningLabel); ?></strong>
                <span><?php esc_html_e('Klaar voor planning', 'bso-survival'); ?></span>
            </article>
        </div>

        <?php if ($widgetsHtml !== '' || $operationsWidgetsHtml !== '') : ?>
            <div class="bso-survival-dashboard__grid">
                <?php echo $widgetsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $operationsWidgetsHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        <?php endif; ?>

        <div class="bso-survival-dashboard__details">
            <details>
                <summary><?php echo esc_html(sprintf(__('Onderdelen (%d)', 'bso-survival'), (int) $overview['counts']['parts'])); ?></summary>
                <ul>
                    <?php foreach ($overview['parts'] as $part) : ?>
                        <li><?php echo esc_html($part->name); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <details>
                <summary><?php echo esc_html(sprintf(__('Teams (%d)', 'bso-survival'), (int) $overview['counts']['teams'])); ?></summary>
                <ul>
                    <?php foreach ($overview['teams'] as $team) : ?>
                        <li><?php echo esc_html($team->name); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
    </div>
</section>

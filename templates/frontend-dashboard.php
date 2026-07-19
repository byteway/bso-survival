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
 * @var array<string, int> $dashboardNavigation
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
$statusNormalizer = static function (string $value): string {
    $trimmed = trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($trimmed) : strtolower($trimmed);
};
$buildPluginAssetUrl = static function (string $relativePath): string {
    if (function_exists('plugins_url') && defined('BSO_SURVIVAL_PLUGIN_FILE')) {
        return (string) plugins_url($relativePath, BSO_SURVIVAL_PLUGIN_FILE);
    }

    return '';
};
$escapeUrl = static function (string $url): string {
    if (function_exists('esc_url')) {
        return esc_url($url);
    }

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
};
$eventStatusLabel = $eventStatus !== '' ? $eventStatus : __('Onbekend', 'bso-survival');
$eventStatusMap = [
    'concept' => 'es-01-idee.svg',
    'idee' => 'es-01-idee.svg',
    'gepland' => 'es-02-voorbereiding.svg',
    'planned' => 'es-02-voorbereiding.svg',
    'voorbereiding' => 'es-02-voorbereiding.svg',
    'actief' => 'es-03-uitvoering.svg',
    'uitvoering' => 'es-03-uitvoering.svg',
    'running' => 'es-03-uitvoering.svg',
    'pauze' => 'es-04-pauze.svg',
    'geblokkeerd' => 'es-05-geblokkeerd.svg',
    'blocked' => 'es-05-geblokkeerd.svg',
    'afgesloten' => 'es-06-gereed.svg',
    'gepubliceerd' => 'es-06-gereed.svg',
    'gereed' => 'es-06-gereed.svg',
    'verwijderd' => 'es-07-geannuleerd.svg',
    'geannuleerd' => 'es-07-geannuleerd.svg',
    'cancelled' => 'es-07-geannuleerd.svg',
    'canceled' => 'es-07-geannuleerd.svg',
];
$eventStatusIcon = $eventStatusMap[$statusNormalizer($eventStatus)] ?? 'es-01-idee.svg';
$eventStatusIconUrl = $buildPluginAssetUrl('assets/images/event-status/' . $eventStatusIcon);
$partsIconUrl = $buildPluginAssetUrl('assets/images/survival-parts.svg');
$teamsIconUrl = $buildPluginAssetUrl('assets/images/survival-teams.svg');
$isRegistrationClosed = !empty($overview['status']['is_registration_full']) || !empty($overview['status']['is_read_only']);
$registrationStatusLabel = $isRegistrationClosed
    ? __('Inschrijving gesloten', 'bso-survival')
    : __('Inschrijving open', 'bso-survival');
$registrationStatusIcon = $isRegistrationClosed ? 'registration-closed.svg' : 'registration-open.svg';
$registrationStatusIconUrl = $buildPluginAssetUrl('assets/images/registration-status/' . $registrationStatusIcon);
$dashboardNavigation = is_array($dashboardNavigation ?? null) ? $dashboardNavigation : [];
$partsHelpPageId = isset($dashboardNavigation['parts_help_page_id']) ? (int) $dashboardNavigation['parts_help_page_id'] : 0;
$teamScorePageId = isset($dashboardNavigation['team_score_page_id']) ? (int) $dashboardNavigation['team_score_page_id'] : 0;
$partsBaseUrl = ($partsHelpPageId > 0 && function_exists('get_permalink')) ? (string) get_permalink($partsHelpPageId) : '';
if (function_exists('apply_filters')) {
    $partsBaseUrl = (string) apply_filters('bso_survival_dashboard_parts_help_url', $partsBaseUrl, $overview, $selectedEventId);
}
$teamScoreBaseUrl = ($teamScorePageId > 0 && function_exists('get_permalink')) ? (string) get_permalink($teamScorePageId) : '';
if (function_exists('apply_filters')) {
    $teamScoreBaseUrl = (string) apply_filters('bso_survival_dashboard_team_score_url', $teamScoreBaseUrl, $overview, $selectedEventId);
}
$buildNavigationUrl = static function (array $args, string $anchor = '', string $baseUrl = '', int $fallbackPageId = 0): string {
    $queryArgs = $args;
    if ($baseUrl === '' && $fallbackPageId > 0) {
        $queryArgs = array_merge(['page_id' => $fallbackPageId], $args);
    }

    $url = '';

    if (function_exists('add_query_arg')) {
        $url = $baseUrl !== ''
            ? (string) add_query_arg($queryArgs, $baseUrl)
            : (string) add_query_arg($queryArgs);
    } else {
        $target = $baseUrl !== '' ? $baseUrl : ((string) ($_SERVER['REQUEST_URI'] ?? ''));
        $separator = strpos($target, '?') === false ? '?' : '&';
        $url = $target . $separator . http_build_query($queryArgs);
    }

    if ($anchor !== '') {
        $url .= '#' . ltrim($anchor, '#');
    }

    return $url;
};
$partsAnchor = 'bso-survival-parts-event-' . $selectedEventId;
$teamScoreAnchor = 'bso-survival-team-score-event-' . $selectedEventId;
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
                <strong class="bso-survival-dashboard__kpi-visual" tabindex="0">
                    <?php if ($eventStatusIconUrl !== '') : ?>
                        <img class="bso-survival-dashboard__kpi-icon" src="<?php echo esc_url($eventStatusIconUrl); ?>" alt="<?php echo esc_attr($eventStatusLabel); ?>" loading="lazy" decoding="async" />
                    <?php endif; ?>
                    <span class="bso-survival-dashboard__kpi-hover-text"><?php echo esc_html($eventStatusLabel); ?></span>
                </strong>
                <span><?php esc_html_e('Status', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong class="bso-survival-dashboard__kpi-inline">
                    <?php if ($partsIconUrl !== '') : ?>
                        <img class="bso-survival-dashboard__kpi-icon" src="<?php echo esc_url($partsIconUrl); ?>" alt="<?php esc_attr_e('Onderdelen', 'bso-survival'); ?>" loading="lazy" decoding="async" />
                    <?php endif; ?>
                    <span class="bso-survival-dashboard__kpi-value"><?php echo esc_html((string) (int) $overview['counts']['parts']); ?></span>
                </strong>
                <span><?php esc_html_e('Onderdelen', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong class="bso-survival-dashboard__kpi-inline">
                    <?php if ($teamsIconUrl !== '') : ?>
                        <img class="bso-survival-dashboard__kpi-icon" src="<?php echo esc_url($teamsIconUrl); ?>" alt="<?php esc_attr_e('Teams', 'bso-survival'); ?>" loading="lazy" decoding="async" />
                    <?php endif; ?>
                    <span class="bso-survival-dashboard__kpi-value"><?php echo esc_html((string) (int) $overview['counts']['teams']); ?></span>
                </strong>
                <span><?php esc_html_e('Teams', 'bso-survival'); ?></span>
            </article>
            <article class="bso-survival-dashboard__kpi-card">
                <strong class="bso-survival-dashboard__kpi-visual" tabindex="0">
                    <?php if ($registrationStatusIconUrl !== '') : ?>
                        <img class="bso-survival-dashboard__kpi-icon" src="<?php echo esc_url($registrationStatusIconUrl); ?>" alt="<?php echo esc_attr($registrationStatusLabel); ?>" loading="lazy" decoding="async" />
                    <?php endif; ?>
                    <span class="bso-survival-dashboard__kpi-hover-text"><?php echo esc_html($registrationStatusLabel); ?></span>
                </strong>
                <span class="bso-survival-dashboard__kpi-subvalue"><?php echo esc_html($registrationLabel); ?></span>
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
                        <?php
                        $partId = (int) ($part->id ?? 0);
                        $partUrl = $partId > 0
                            ? $buildNavigationUrl(
                                [
                                    'event_id' => $selectedEventId,
                                    'part_id' => $partId,
                                ],
                                $partsAnchor,
                                $partsBaseUrl,
                                $pageId
                            )
                            : '';
                        ?>
                        <li>
                            <?php if ($partUrl !== '') : ?>
                                <a href="<?php echo $escapeUrl($partUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) ($part->name ?? '')); ?></a>
                            <?php else : ?>
                                <?php echo esc_html((string) ($part->name ?? '')); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>

            <details>
                <summary><?php echo esc_html(sprintf(__('Teams (%d)', 'bso-survival'), (int) $overview['counts']['teams'])); ?></summary>
                <ul>
                    <?php foreach ($overview['teams'] as $team) : ?>
                        <?php
                        $teamId = (int) ($team->id ?? 0);
                        $teamUrl = $teamId > 0
                            ? $buildNavigationUrl(
                                [
                                    'event_id' => $selectedEventId,
                                    'team_id' => $teamId,
                                ],
                                $teamScoreAnchor,
                                $teamScoreBaseUrl,
                                $pageId
                            )
                            : '';
                        ?>
                        <li>
                            <?php if ($teamUrl !== '') : ?>
                                <a href="<?php echo $escapeUrl($teamUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) ($team->name ?? '')); ?></a>
                            <?php else : ?>
                                <?php echo esc_html((string) ($team->name ?? '')); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
    </div>
</section>

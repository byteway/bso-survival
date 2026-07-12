<?php
/**
 * Frontend team score template for shortcode output.
 *
 * @var string $title
 * @var object $event
 * @var object $team
 * @var array<int, object> $teams
 * @var int $teamId
 * @var array<string, mixed> $overview
 * @var bool $canEditScores
 */

$rows = is_array($overview['rows'] ?? null) ? $overview['rows'] : [];
$teams = is_array($teams ?? null) ? $teams : [];
$teamId = isset($teamId) ? (int) $teamId : (int) ($team->id ?? 0);
$pageId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
$canEditScores = !empty($canEditScores);
$editorId = 'bso-team-score-editor-' . uniqid();
$restUpdateBase = function_exists('rest_url') ? (string) rest_url('bso-survival/v1/scores/entries') : '';
$restNonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce('wp_rest') : '';

$sortableColumns = [
    'part' => ['field' => 'part_name', 'type' => 'text'],
    'timeslot' => ['field' => 'timeslot_sort', 'type' => 'number'],
    'raw' => ['field' => 'raw_value', 'type' => 'number'],
    'joker' => ['field' => 'joker_applied', 'type' => 'number'],
    'position' => ['field' => 'provisional_position', 'type' => 'number'],
];

if ($canEditScores) {
    $sortableColumns['bonus'] = ['field' => 'bonus_points', 'type' => 'number'];
    $sortableColumns['interim'] = ['field' => 'interim_score', 'type' => 'number'];
}

$activeSortBy = isset($_GET['team_sort_by']) ? sanitize_key((string) $_GET['team_sort_by']) : 'timeslot';
$activeSortDir = isset($_GET['team_sort_dir']) && strtolower((string) $_GET['team_sort_dir']) === 'desc' ? 'desc' : 'asc';

if (!isset($sortableColumns[$activeSortBy])) {
    $activeSortBy = 'timeslot';
}

if ($activeSortBy === 'timeslot') {
    // Keep timeslot view stable from early to late, regardless of stale URL params.
    $activeSortDir = 'asc';
}

$activeSortConfig = $sortableColumns[$activeSortBy];
usort($rows, static function (array $a, array $b) use ($activeSortConfig, $activeSortDir): int {
    $field = (string) $activeSortConfig['field'];
    $type = (string) $activeSortConfig['type'];

    if ($type === 'number') {
        $aValue = isset($a[$field]) ? (float) $a[$field] : 0.0;
        $bValue = isset($b[$field]) ? (float) $b[$field] : 0.0;

        if ($aValue === $bValue) {
            $aTie = isset($a['part_name']) ? (string) $a['part_name'] : '';
            $bTie = isset($b['part_name']) ? (string) $b['part_name'] : '';
            $cmp = strcasecmp($aTie, $bTie);
            return $cmp !== 0 ? $cmp : (((int) ($a['assignment_id'] ?? 0)) <=> ((int) ($b['assignment_id'] ?? 0)));
        }

        return $activeSortDir === 'asc' ? ($aValue <=> $bValue) : ($bValue <=> $aValue);
    }

    $aValue = isset($a[$field]) ? (string) $a[$field] : '';
    $bValue = isset($b[$field]) ? (string) $b[$field] : '';
    $cmp = strnatcasecmp($aValue, $bValue);

    if ($cmp === 0) {
        return ((int) ($a['assignment_id'] ?? 0)) <=> ((int) ($b['assignment_id'] ?? 0));
    }

    return $activeSortDir === 'asc' ? $cmp : -$cmp;
});

$buildSortUrl = static function (string $columnKey) use ($activeSortBy, $activeSortDir): string {
    $nextDir = ($activeSortBy === $columnKey && $activeSortDir === 'asc') ? 'desc' : 'asc';
    if ($columnKey === 'timeslot') {
        $nextDir = 'asc';
    }
    $base = remove_query_arg(['team_sort_by', 'team_sort_dir']);

    return add_query_arg(
        [
            'team_sort_by' => $columnKey,
            'team_sort_dir' => $nextDir,
        ],
        $base
    );
};

$sortIndicator = static function (string $columnKey) use ($activeSortBy, $activeSortDir): string {
    if ($activeSortBy !== $columnKey) {
        return '<span class="bso-sort-indicator bso-sort-indicator--inactive" aria-hidden="true">&#8597;</span>';
    }

    if ($activeSortDir === 'asc') {
        return '<span class="bso-sort-indicator bso-sort-indicator--active" aria-hidden="true">&#8593;</span>';
    }

    return '<span class="bso-sort-indicator bso-sort-indicator--active" aria-hidden="true">&#8595;</span>';
};
?>
<section class="bso-survival-team-score<?php echo $canEditScores ? ' bso-survival-team-score--editable' : ''; ?>" id="<?php echo esc_attr($editorId); ?>" data-team-score-editor="1" data-can-edit="<?php echo $canEditScores ? '1' : '0'; ?>" data-event-id="<?php echo (int) $event->id; ?>" data-rest-update-base="<?php echo esc_attr($restUpdateBase); ?>" data-rest-nonce="<?php echo esc_attr($restNonce); ?>">
    <header class="bso-survival-team-score__header">
        <h2><?php echo esc_html($title); ?></h2>
        <?php if ($teams !== []) : ?>
            <form class="bso-survival-score-selector" method="get">
                <?php if ($pageId > 0) : ?>
                    <input type="hidden" name="page_id" value="<?php echo $pageId; ?>" />
                <?php endif; ?>
                <input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>" />
                <label for="bso-team-score-team-id"><?php esc_html_e('Team', 'bso-survival'); ?></label>
                <select id="bso-team-score-team-id" name="team_id" onchange="this.form.submit()">
                    <?php foreach ($teams as $teamOption) : ?>
                        <?php $optionTeamId = (int) ($teamOption->id ?? 0); ?>
                        <option value="<?php echo $optionTeamId; ?>"<?php echo $optionTeamId === $teamId ? ' selected="selected"' : ''; ?>>
                            <?php echo esc_html((string) ($teamOption->name ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Toon team', 'bso-survival'); ?></button>
                </noscript>
            </form>
        <?php endif; ?>
        <p class="bso-survival-team-score__meta">
            <?php echo esc_html(sprintf('Event #%d - %s | Team: %s', (int) $event->id, (string) $event->name, (string) $team->name)); ?>
        </p>
        <p class="bso-survival-part-score__permission-note <?php echo $canEditScores ? 'is-editor' : 'is-readonly'; ?>">
            <?php echo esc_html($canEditScores ? __('Rol: scorebeheerder (bewerken toegestaan).', 'bso-survival') : __('Rol: alleen-lezen (bewerken niet toegestaan).', 'bso-survival')); ?>
        </p>
    </header>

    <div class="bso-survival-team-score__summary">
        <p><?php echo esc_html(sprintf('Afgeronde onderdelen: %d', (int) ($overview['counts']['completed'] ?? 0))); ?></p>
        <p><?php echo esc_html(sprintf('Open onderdelen: %d', (int) ($overview['counts']['pending'] ?? 0))); ?></p>
        <p><?php echo esc_html(sprintf('Joker ingezet: %d', (int) ($overview['counts']['joker_count'] ?? 0))); ?></p>
        <p><strong><?php echo esc_html(sprintf('Tussentijdse eindscore: %d', (int) ($overview['counts']['interim_total'] ?? 0))); ?></strong></p>
    </div>

    <?php if ($canEditScores) : ?>
        <p class="bso-survival-part-score__editor-hint"><?php esc_html_e('Klik op een score-rij om rechts te bewerken.', 'bso-survival'); ?></p>
    <?php endif; ?>

    <?php if (empty($rows)) : ?>
        <p><?php esc_html_e('Geen scoregegevens gevonden voor dit team.', 'bso-survival'); ?></p>
    <?php else : ?>
        <div class="bso-survival-team-score__table-wrap">
            <table class="bso-survival-team-score__table">
                <thead>
                    <tr>
                        <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('timeslot')); ?>"><?php esc_html_e('Tijdsrange', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('timeslot')); ?></a></th>
                        <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('part')); ?>"><?php esc_html_e('Onderdeel', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('part')); ?></a></th>
                        <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('raw')); ?>"><?php esc_html_e('Ruwe score', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('raw')); ?></a></th>
                        <?php if ($canEditScores) : ?>
                            <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('bonus')); ?>"><?php esc_html_e('Bonus', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('bonus')); ?></a></th>
                        <?php endif; ?>
                        <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('joker')); ?>"><?php esc_html_e('Joker', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('joker')); ?></a></th>
                        <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('position')); ?>"><?php esc_html_e('Positie', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('position')); ?></a></th>
                        <?php if ($canEditScores) : ?>
                            <th><a class="bso-sort-link" href="<?php echo esc_url($buildSortUrl('interim')); ?>"><?php esc_html_e('Tussentijdse score', 'bso-survival'); ?> <?php echo wp_kses_post($sortIndicator('interim')); ?></a></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $scoreEntryId = (int) ($row['score_entry_id'] ?? 0);
                        $isRowEditable = $canEditScores && $scoreEntryId > 0;
                        $timeslotLabel = (string) ($row['timeslot_range'] ?? __('Geen tijdslot', 'bso-survival'));
                        ?>
                        <tr<?php echo $isRowEditable ? ' class="bso-team-score-row-clickable"' : ''; ?> data-score-entry-id="<?php echo $scoreEntryId; ?>" data-team-name="<?php echo esc_attr((string) ($team->name ?? '')); ?>" data-part-name="<?php echo esc_attr((string) ($row['part_name'] ?? '')); ?>" data-timeslot-label="<?php echo esc_attr($timeslotLabel); ?>" data-raw-value="<?php echo esc_attr((string) ($row['raw_value'] ?? '0')); ?>" data-bonus-points="<?php echo esc_attr((string) ($row['bonus_points'] ?? '0')); ?>" data-joker-applied="<?php echo !empty($row['joker_applied']) ? '1' : '0'; ?>" data-editable="<?php echo $isRowEditable ? '1' : '0'; ?>">
                            <td><span class="bso-survival-timeslot-tag"><?php echo esc_html((string) ($row['timeslot_range'] ?? __('Geen tijdslot', 'bso-survival'))); ?></span></td>
                            <td><?php echo esc_html((string) $row['part_name']); ?></td>
                            <td><?php echo esc_html(!empty($row['is_completed']) ? number_format((float) $row['raw_value'], 4, '.', '') : '-'); ?></td>
                            <?php if ($canEditScores) : ?>
                                <td><?php echo esc_html(!empty($row['is_completed']) ? number_format((float) ($row['bonus_points'] ?? 0), 2, '.', '') : '-'); ?></td>
                            <?php endif; ?>
                            <td><?php echo esc_html(!empty($row['joker_applied']) ? __('Ja', 'bso-survival') : __('Nee', 'bso-survival')); ?></td>
                            <td><?php echo esc_html((string) (int) ($row['provisional_position'] ?? 0)); ?></td>
                            <?php if ($canEditScores) : ?>
                                <td><?php echo esc_html((string) (int) ($row['interim_score'] ?? 0)); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($canEditScores) : ?>
            <aside class="bso-team-score-editor" data-score-editor-panel="1" hidden="hidden" aria-hidden="true">
                <div class="bso-team-score-editor__top">
                    <h3 class="bso-team-score-editor__title"><?php esc_html_e('Bewerken score', 'bso-survival'); ?></h3>
                    <button type="button" class="button button-link" data-score-editor-close="1"><?php esc_html_e('Sluiten', 'bso-survival'); ?></button>
                </div>

                <p class="bso-team-score-editor__context" data-score-editor-context="1"></p>

                <p class="bso-team-score-editor__status" data-score-editor-status="1" hidden="hidden"></p>

                <form data-score-editor-form="1">
                    <input type="hidden" name="score_entry_id" value="" data-score-editor-score-id="1" />

                    <p>
                        <label for="<?php echo esc_attr($editorId); ?>-raw"><strong><?php esc_html_e('Ruwe score', 'bso-survival'); ?></strong></label><br />
                        <input id="<?php echo esc_attr($editorId); ?>-raw" type="number" step="0.01" name="raw_value" required="required" data-score-editor-raw="1" style="width:100%;max-width:100%;" />
                    </p>

                    <p>
                        <label for="<?php echo esc_attr($editorId); ?>-bonus"><strong><?php esc_html_e('Bonus punten', 'bso-survival'); ?></strong></label><br />
                        <input id="<?php echo esc_attr($editorId); ?>-bonus" type="number" min="0" step="0.01" name="bonus_points" value="0" data-score-editor-bonus="1" style="width:100%;max-width:100%;" />
                    </p>

                    <p>
                        <label><input type="checkbox" name="joker_applied" value="1" data-score-editor-joker="1" /> <?php esc_html_e('Joker ingezet (score telt dubbel)', 'bso-survival'); ?></label>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary" data-score-editor-save="1"><?php esc_html_e('Opslaan', 'bso-survival'); ?></button>
                        <button type="button" class="button" data-score-editor-cancel="1"><?php esc_html_e('Annuleren', 'bso-survival'); ?></button>
                    </p>
                </form>
            </aside>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
/**
 * Frontend team score template for shortcode output.
 *
 * @var string $title
 * @var object $event
 * @var object $team
 * @var array<string, mixed> $overview
 * @var bool $canEditScores
 */

$rows = is_array($overview['rows'] ?? null) ? $overview['rows'] : [];
$canEditScores = !empty($canEditScores);

$sortableColumns = [
    'part' => ['field' => 'part_name', 'type' => 'text'],
    'raw' => ['field' => 'raw_value', 'type' => 'number'],
    'joker' => ['field' => 'joker_applied', 'type' => 'number'],
    'position' => ['field' => 'provisional_position', 'type' => 'number'],
];

if ($canEditScores) {
    $sortableColumns['bonus'] = ['field' => 'bonus_points', 'type' => 'number'];
    $sortableColumns['interim'] = ['field' => 'interim_score', 'type' => 'number'];
}

$activeSortBy = isset($_GET['team_sort_by']) ? sanitize_key((string) $_GET['team_sort_by']) : 'position';
$activeSortDir = isset($_GET['team_sort_dir']) && strtolower((string) $_GET['team_sort_dir']) === 'desc' ? 'desc' : 'asc';

if (!isset($sortableColumns[$activeSortBy])) {
    $activeSortBy = 'position';
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
<section class="bso-survival-team-score">
    <header class="bso-survival-team-score__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-team-score__meta">
            <?php echo esc_html(sprintf('Event #%d - %s | Team: %s', (int) $event->id, (string) $event->name, (string) $team->name)); ?>
        </p>
    </header>

    <div class="bso-survival-team-score__summary">
        <p><?php echo esc_html(sprintf('Afgeronde onderdelen: %d', (int) ($overview['counts']['completed'] ?? 0))); ?></p>
        <p><?php echo esc_html(sprintf('Open onderdelen: %d', (int) ($overview['counts']['pending'] ?? 0))); ?></p>
        <p><?php echo esc_html(sprintf('Joker ingezet: %d', (int) ($overview['counts']['joker_count'] ?? 0))); ?></p>
        <p><strong><?php echo esc_html(sprintf('Tussentijdse eindscore: %d', (int) ($overview['counts']['interim_total'] ?? 0))); ?></strong></p>
    </div>

    <?php if (empty($rows)) : ?>
        <p><?php esc_html_e('Geen scoregegevens gevonden voor dit team.', 'bso-survival'); ?></p>
    <?php else : ?>
        <div class="bso-survival-team-score__table-wrap">
            <table class="bso-survival-team-score__table">
                <thead>
                    <tr>
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
                        <tr>
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
    <?php endif; ?>
</section>
<?php

use BSO\Survival\Support\MetaDataHelper;

$selectedPartId = max(0, (int) ($selectedPart->id ?? 0));
$selectedPartName = (string) ($selectedPart->name ?? '');
$parts = is_array($parts ?? null) ? $parts : [];
$gridRows = is_array($gridRows ?? null) ? $gridRows : [];
$pageId = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

$actionUrl = function_exists('remove_query_arg') ? (string) remove_query_arg('part_id') : '';
?>
<section class="bso-survival-timeslot-board" data-timeslot-board="1" data-event-id="<?php echo (int) $event->id; ?>">
    <div class="bso-survival-timeslot-board__header">
        <div>
            <h2 class="bso-survival-timeslot-board__title"><?php echo esc_html($title); ?></h2>
            <p class="bso-survival-timeslot-board__subtitle">
                <?php echo esc_html(sprintf(__('Event: %s', 'bso-survival'), (string) ($event->name ?? ''))); ?>
            </p>
        </div>
        <?php if ($selectedPartName !== ''): ?>
            <div class="bso-survival-timeslot-board__selection">
                <?php echo esc_html(sprintf(__('Geselecteerd onderdeel: %s', 'bso-survival'), $selectedPartName)); ?>
            </div>
        <?php endif; ?>
    </div>

    <form class="bso-survival-timeslot-board__filter" method="get" action="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>" />
        <?php if ($pageId > 0): ?>
            <input type="hidden" name="page_id" value="<?php echo $pageId; ?>" />
        <?php endif; ?>
        <label for="bso-timeslot-board-part"><?php esc_html_e('Onderdeel', 'bso-survival'); ?></label>
        <select id="bso-timeslot-board-part" name="part_id" onchange="this.form.submit()">
            <?php foreach ($parts as $part): ?>
                <?php $partId = (int) ($part->id ?? 0); ?>
                <option value="<?php echo $partId; ?>"<?php echo $selectedPartId === $partId ? ' selected="selected"' : ''; ?>><?php echo esc_html((string) ($part->name ?? '')); ?></option>
            <?php endforeach; ?>
        </select>
        <noscript>
            <button type="submit" class="button button-primary"><?php esc_html_e('Toon overzicht', 'bso-survival'); ?></button>
        </noscript>
    </form>

    <div class="bso-survival-timeslot-board__table-wrap">
        <table class="bso-survival-timeslot-board__table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Tijdslot', 'bso-survival'); ?></th>
                    <th><?php esc_html_e('Team A', 'bso-survival'); ?></th>
                    <th><?php esc_html_e('Team B', 'bso-survival'); ?></th>
                    <th><?php esc_html_e('Status', 'bso-survival'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($gridRows === []): ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('Geen tijdsloten gevonden voor dit onderdeel.', 'bso-survival'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($gridRows as $row): ?>
                        <?php
                        $teamA = is_array($row['team_a'] ?? null) ? $row['team_a'] : [];
                        $teamB = is_array($row['team_b'] ?? null) ? $row['team_b'] : [];
                        $hasScoreA = !empty($teamA['is_completed']);
                        $hasScoreB = !empty($teamB['is_completed']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(sprintf(__('Tijdslot %d', 'bso-survival'), (int) ($row['slot_number'] ?? $row['timeslot_id'] ?? 0))); ?></strong><br />
                                <span class="bso-survival-timeslot-board__part-name"><?php echo esc_html((string) ($row['part_name'] ?? '')); ?></span>
                            </td>
                            <td><?php echo esc_html((string) ($teamA['team_name'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($teamB['team_name'] ?? '')); ?></td>
                            <td class="bso-survival-timeslot-board__status-cell">
                                <span class="bso-survival-timeslot-board__status-item" title="<?php echo esc_attr(sprintf('%s: %s', (string) ($teamA['team_name'] ?? __('Team A', 'bso-survival')), $hasScoreA ? __('Score aanwezig', 'bso-survival') : __('Geen score', 'bso-survival'))); ?>">
                                    <span class="bso-survival-timeslot-board__led <?php echo $hasScoreA ? 'is-on' : 'is-off'; ?>"></span>
                                    <span class="bso-survival-timeslot-board__status-label"><?php echo esc_html((string) ($teamA['team_name'] ?? __('Team A', 'bso-survival'))); ?></span>
                                </span>
                                <span class="bso-survival-timeslot-board__status-item" title="<?php echo esc_attr(sprintf('%s: %s', (string) ($teamB['team_name'] ?? __('Team B', 'bso-survival')), $hasScoreB ? __('Score aanwezig', 'bso-survival') : __('Geen score', 'bso-survival'))); ?>">
                                    <span class="bso-survival-timeslot-board__led <?php echo $hasScoreB ? 'is-on' : 'is-off'; ?>"></span>
                                    <span class="bso-survival-timeslot-board__status-label"><?php echo esc_html((string) ($teamB['team_name'] ?? __('Team B', 'bso-survival'))); ?></span>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

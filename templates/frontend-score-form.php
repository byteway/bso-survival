<?php
/**
 * Frontend score form template.
 *
 * @var string $title
 * @var string $buttonLabel
 * @var int $eventId
 * @var object $event
 * @var array<string, mixed> $overview
 * @var bool $isReadOnly
 * @var array<int, object> $assignments
 * @var string $restUrl
 * @var string $nonce
 * @var string $partStatusRestBase
 * @var string $restNonce
 */
?>
<section class="bso-survival-score-form">
    <header class="bso-survival-score-form__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-score-form__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $event->id, (string) $event->name)); ?>
        </p>
    </header>

    <?php if (!empty($overview['status']['is_read_only'])) : ?>
        <div class="bso-survival-status-notice bso-survival-status-notice--readonly">
            <p><?php echo esc_html(__('Dit event is afgesloten. Score-invoer is read-only geblokkeerd.', 'bso-survival')); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($overview['status']['is_published'])) : ?>
        <div class="bso-survival-status-notice bso-survival-status-notice--published">
            <p><?php echo esc_html(__('De eindstand is gepubliceerd. Nieuwe score-invoer is niet toegestaan.', 'bso-survival')); ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($assignments)) : ?>
        <div class="bso-survival-status-notice bso-survival-status-notice--readonly">
            <p><?php echo esc_html(__('Geen assignments gevonden voor dit event. Score-invoer is tijdelijk niet mogelijk.', 'bso-survival')); ?></p>
        </div>
    <?php endif; ?>

    <form id="bso-score-form"
          data-rest-url="<?php echo esc_attr($restUrl); ?>"
          data-score-nonce="<?php echo esc_attr($nonce); ?>"
            data-part-status-base="<?php echo esc_attr($partStatusRestBase); ?>"
            data-rest-nonce="<?php echo esc_attr($restNonce); ?>"
          data-event-id="<?php echo (int) $eventId; ?>"
          data-is-read-only="<?php echo $isReadOnly ? '1' : '0'; ?>"
          <?php echo empty($assignments) ? 'data-has-assignments="0"' : 'data-has-assignments="1"'; ?> >
        <p>
            <label for="bso-score-assignment-id"><?php esc_html_e('Assignment', 'bso-survival'); ?></label><br />
            <select id="bso-score-assignment-id" name="assignment_id" <?php echo $isReadOnly || empty($assignments) ? 'disabled="disabled"' : ''; ?> required="required">
                <option value=""><?php esc_html_e('Kies een assignment', 'bso-survival'); ?></option>
                <?php foreach ($assignments as $assignment) : ?>
                    <option value="<?php echo (int) $assignment->id; ?>" data-part-id="<?php echo (int) ($assignment->part_id ?? 0); ?>">
                        <?php echo esc_html(sprintf('%s - %s', (string) ($assignment->team_name ?? 'Onbekend team'), (string) ($assignment->part_name ?? 'Onbekend onderdeel'))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="bso-score-raw-value"><?php esc_html_e('Ruwe score', 'bso-survival'); ?></label><br />
            <input id="bso-score-raw-value" name="raw_value" type="number" step="0.01" <?php echo $isReadOnly || empty($assignments) ? 'disabled="disabled"' : ''; ?> required="required" />
        </p>

        <p>
            <button id="bso-score-submit" type="submit" class="button button-primary" <?php echo $isReadOnly || empty($assignments) ? 'disabled="disabled"' : ''; ?>><?php echo esc_html($buttonLabel); ?></button>
        </p>

        <div id="bso-score-confirm-wrap" class="bso-survival-score-form__confirm" hidden="hidden">
            <p id="bso-score-confirm-text" class="bso-survival-score-form__confirm-text"></p>
            <p>
                <button id="bso-score-confirm-button" type="button" class="button button-secondary"><?php esc_html_e('Onderdeel bevestigen', 'bso-survival'); ?></button>
            </p>
        </div>

        <div id="bso-score-status" class="bso-survival-status-notice" style="display:none;"><p></p></div>
    </form>
</section>

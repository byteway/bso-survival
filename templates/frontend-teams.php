<?php
/**
 * Frontend teams template for shortcode output.
 *
 * @var string $title
 * @var object $event
 * @var array<int, object> $teams
 */
?>
<section class="bso-survival-teams">
    <header class="bso-survival-teams__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-teams__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $event->id, (string) $event->name)); ?>
        </p>
    </header>

    <?php if (empty($teams)) : ?>
        <p><?php esc_html_e('Geen teams gevonden voor dit event.', 'bso-survival'); ?></p>
    <?php else : ?>
        <ul class="bso-survival-teams__list">
            <?php foreach ($teams as $team) : ?>
                <li><?php echo esc_html((string) $team->name); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
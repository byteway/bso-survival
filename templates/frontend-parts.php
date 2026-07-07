<?php
/**
 * Frontend parts template for shortcode output.
 *
 * @var string $title
 * @var object $event
 * @var array<int, object> $parts
 */
?>
<section class="bso-survival-parts">
    <header class="bso-survival-parts__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-parts__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $event->id, (string) $event->name)); ?>
        </p>
    </header>

    <?php if (empty($parts)) : ?>
        <p><?php esc_html_e('Geen onderdelen gevonden voor dit event.', 'bso-survival'); ?></p>
    <?php else : ?>
        <ul class="bso-survival-parts__list">
            <?php foreach ($parts as $part) : ?>
                <li><?php echo esc_html((string) $part->name); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
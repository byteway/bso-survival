<?php
/**
 * Frontend parts template for shortcode output.
 *
 * @var string $title
 * @var object $event
 * @var array<int, object> $parts
 * @var object $selectedPart
 * @var array{html:string, images:array<int, string>, context:array<string, string>} $selectedHelp
 * @var array{prev:object|null,next:object|null} $links
 */

$currentPartId = (int) ($selectedPart->id ?? 0);

$buildPartUrl = static function (int $partId): string {
    $query = $_GET;
    if (!is_array($query)) {
        $query = [];
    }

    foreach ($query as $key => $value) {
        if (!is_scalar($value)) {
            unset($query[$key]);
            continue;
        }

        $query[$key] = sanitize_text_field((string) wp_unslash($value));
    }

    $query['part_id'] = $partId;

    if (function_exists('add_query_arg')) {
        return (string) add_query_arg($query);
    }

    $base = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $separator = strpos($base, '?') === false ? '?' : '&';

    return $base . $separator . http_build_query($query);
};

$escapeUrl = static function (string $url): string {
    if (function_exists('esc_url')) {
        return esc_url($url);
    }

    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
};

$translate = static function (string $text): string {
    if (function_exists('__')) {
        return (string) __($text, 'bso-survival');
    }

    return $text;
};

$escapeHtml = static function (string $text): string {
    if (function_exists('esc_html')) {
        return esc_html($text);
    }

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
};

$escapeAttr = static function (string $text): string {
    if (function_exists('esc_attr')) {
        return esc_attr($text);
    }

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
};

$sectionAnchorId = 'bso-survival-parts-event-' . (int) ($event->id ?? 0);
?>
<section class="bso-survival-parts" id="<?php echo esc_attr($sectionAnchorId); ?>">
    <header class="bso-survival-parts__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-parts__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $event->id, (string) $event->name)); ?>
        </p>
    </header>

    <?php if (empty($parts)) : ?>
        <p><?php echo $escapeHtml($translate('Geen onderdelen gevonden voor dit event.')); ?></p>
    <?php else : ?>
        <div class="bso-survival-parts__mobile-select-wrap">
            <label for="bso-survival-part-select"><?php echo $escapeHtml($translate('Kies onderdeel')); ?></label>
            <select id="bso-survival-part-select" class="bso-survival-parts__mobile-select" data-nav-select="parts-help">
                <?php foreach ($parts as $part) : ?>
                    <?php
                    $partId = (int) ($part->id ?? 0);
                    $partUrl = $buildPartUrl($partId);
                    ?>
                    <option value="<?php echo esc_attr($partUrl); ?>" <?php echo $currentPartId === $partId ? 'selected="selected"' : ''; ?>>
                        <?php echo esc_html((string) ($part->name ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="bso-survival-parts__layout">
            <nav class="bso-survival-parts__menu" aria-label="<?php echo $escapeAttr($translate('Onderdelen navigatie')); ?>">
                <ul class="bso-survival-parts__list">
                    <?php foreach ($parts as $part) : ?>
                        <?php
                        $partId = (int) ($part->id ?? 0);
                        $isActive = $partId === $currentPartId;
                        ?>
                        <li>
                            <a class="bso-survival-parts__item<?php echo $isActive ? ' is-active' : ''; ?>" href="<?php echo $escapeUrl($buildPartUrl($partId)); ?>" <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
                                <?php echo esc_html((string) ($part->name ?? '')); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <article class="bso-survival-parts__detail">
                <header class="bso-survival-parts__detail-header">
                    <h3><?php echo esc_html((string) ($selectedPart->name ?? '')); ?></h3>
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                'GPS: %s, %s',
                                (string) ($selectedPart->latitude ?? '-'),
                                (string) ($selectedPart->longitude ?? '-')
                            )
                        );
                        ?>
                    </p>
                </header>

                <div class="bso-survival-parts__help-body">
                    <?php
                    $helpHtml = (string) ($selectedHelp['html'] ?? '');
                    echo function_exists('wp_kses_post') ? wp_kses_post($helpHtml) : $helpHtml;
                    ?>
                </div>

                <?php if (!empty($selectedHelp['images'])) : ?>
                    <div class="bso-survival-parts__gallery">
                        <?php foreach ($selectedHelp['images'] as $index => $imageUrl) : ?>
                            <figure class="bso-survival-parts__figure">
                                <img src="<?php echo esc_url((string) $imageUrl); ?>" alt="<?php echo esc_attr(sprintf('%s - afbeelding %d', (string) ($selectedPart->name ?? 'Onderdeel'), ((int) $index + 1))); ?>" loading="lazy" decoding="async" />
                            </figure>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <footer class="bso-survival-parts__pager" aria-label="<?php echo $escapeAttr($translate('Blader door helpteksten')); ?>">
                    <?php if (is_object($links['prev'])) : ?>
                        <?php $prevId = (int) ($links['prev']->id ?? 0); ?>
                        <a class="bso-survival-parts__pager-btn" href="<?php echo $escapeUrl($buildPartUrl($prevId)); ?>">
                            <?php echo $escapeHtml($translate('Vorige onderdeel')); ?>
                        </a>
                    <?php else : ?>
                        <span class="bso-survival-parts__pager-btn is-disabled"><?php echo $escapeHtml($translate('Vorige onderdeel')); ?></span>
                    <?php endif; ?>

                    <?php if (is_object($links['next'])) : ?>
                        <?php $nextId = (int) ($links['next']->id ?? 0); ?>
                        <a class="bso-survival-parts__pager-btn" href="<?php echo $escapeUrl($buildPartUrl($nextId)); ?>">
                            <?php echo $escapeHtml($translate('Volgende onderdeel')); ?>
                        </a>
                    <?php else : ?>
                        <span class="bso-survival-parts__pager-btn is-disabled"><?php echo $escapeHtml($translate('Volgende onderdeel')); ?></span>
                    <?php endif; ?>
                </footer>
            </article>
        </div>
    <?php endif; ?>
</section>
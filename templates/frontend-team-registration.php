<?php
/**
 * Frontend team registration template.
 *
 * @var string $title
 * @var string $buttonLabel
 * @var int $eventId
 * @var object $event
 * @var string $restUrl
 * @var string $nonce
 */
?>
<section class="bso-survival-registration">
    <header class="bso-survival-registration__header">
        <h2><?php echo esc_html($title); ?></h2>
        <p class="bso-survival-registration__meta">
            <?php echo esc_html(sprintf('Event #%d - %s', (int) $event->id, (string) $event->name)); ?>
        </p>
    </header>

    <form id="bso-team-registration-form"
          data-rest-url="<?php echo esc_attr($restUrl); ?>"
          data-registration-nonce="<?php echo esc_attr($nonce); ?>"
          data-event-id="<?php echo (int) $eventId; ?>">
        <p>
            <label for="bso-registration-team-name"><?php esc_html_e('Teamnaam', 'bso-survival'); ?></label><br />
            <input id="bso-registration-team-name" name="team_name" type="text" required="required" class="regular-text" />
        </p>

        <p>
            <label for="bso-registration-contact-name"><?php esc_html_e('Contactpersoon', 'bso-survival'); ?></label><br />
            <input id="bso-registration-contact-name" name="contact_name" type="text" required="required" class="regular-text" />
        </p>

        <p>
            <label for="bso-registration-contact-email"><?php esc_html_e('E-mail', 'bso-survival'); ?></label><br />
            <input id="bso-registration-contact-email" name="contact_email" type="email" required="required" class="regular-text" />
        </p>

        <p>
            <label for="bso-registration-contact-phone"><?php esc_html_e('Telefoon', 'bso-survival'); ?></label><br />
            <input id="bso-registration-contact-phone" name="contact_phone" type="text" required="required" class="regular-text" />
        </p>

        <p>
            <label for="bso-registration-team-members"><?php esc_html_e('Teamleden (1 per regel)', 'bso-survival'); ?></label><br />
            <textarea id="bso-registration-team-members" name="team_members" required="required" rows="6" class="large-text"></textarea>
        </p>

        <p>
            <button id="bso-registration-submit" type="submit" class="button button-primary"><?php echo esc_html($buttonLabel); ?></button>
        </p>

        <div id="bso-registration-status" class="bso-survival-status-notice" style="display:none;"><p></p></div>
    </form>
</section>

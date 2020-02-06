<?php
/**
 * The template for displaying the webhook_url field input.
 *
 * @author  Jacob Vega/Canote
 * @package SimpleHistory/SlackNotifierDropin/Templates
 * @since   0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

                    <p><input id="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>webhook_url" type="url" class="regular-text ltr" placeholder="<?php esc_attr_e('https://hooks.slack.com/services/ABC123/B123456/O4j88fj33bbbbjjkkfssd', 'simple-history') ?>" name="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>webhook_url" value="<?php echo esc_attr($notifier_webhook_url) ?>" /></p>
                    <p class="description"><small><?php printf(__('Create an %1$sincoming WebHook%2$s and then paste the WebHook URL here.', 'simple-history'), '<a href="https://my.slack.com/services/new/incoming-webhook/">', '</a>') ?></small></p>
                <?php ?>
<?php
/**
 * The template for displaying the enabled field input.
 *
 * @author  WEBDOGS
 * @package SimpleHistory/SlackNotifierDropin/Templates
 * @since   0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
        <input <?php checked($notifier_enabled, true) ?> value="1" type="checkbox" id="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>enabled" name="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>enabled" onchange="jQuery(':input',jQuery(this).parents('.form-table')).not(this).parents('tr').toggleClass('hidden',!jQuery(this).is(':checked'))" />
        <label for="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>enabled"><?php _e('Enable Slack notifications', 'simple-history') ?></label>

<?php
/**
 * The template for displaying the delay field input.
 *
 * @author  WEBDOGS
 * @package SimpleHistory/SlackNotifierDropin/Templates
 * @since   0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

        <p>
            <select id="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>delay" name="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>delay" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--date regular-text" placeholder="<?php esc_attr_e('Default delay', 'simple-history') ?>">
                <option <?php selected($notifier_delay, '+10 seconds') ?> value="+10 seconds"><?php _ex('After 10 seconds', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+30 seconds') ?> value="+30 seconds"><?php _ex('After 30 seconds', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+1 minute') ?> value="+1 minute"><?php _ex('After 1 minute', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+5 minutes') ?> value="+5 minutes"><?php _ex('After 5 minutes', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+30 minutes') ?> value="+30 minutes"><?php _ex('After 30 minutes', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+1 hour') ?> value="+1 hour"><?php _ex('After an hour', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+1 day') ?> value="+1 day"><?php _ex('After a day', 'Option duration', 'simple-history') ?></option>
            </select>
        </p>

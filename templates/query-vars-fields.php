<?php
/**
 * The template for displaying the query_vars fields input.
 *
 * @author  Jacob Vega/Canote
 * @package SimpleHistory/SlackNotifierDropin/Templates
 * @since   0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

                    <p>
                        <select id="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>query_vars" name="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>query_vars[loglevels][]" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--loglevel regular-text" placeholder="<?php esc_attr_e('All log levels', 'simple-history') ?>" multiple>
                            <option <?php selected(in_array('debug', $notifier_query_loglevels), true) ?> value="debug" data-color="#CEF6D8"><?php echo $this->sh->getLogLevelTranslated('Debug') ?></option>
                            <option <?php selected(in_array('info', $notifier_query_loglevels), true) ?> value="info" data-color="#FFFF"><?php echo $this->sh->getLogLevelTranslated('Info') ?></option>
                            <option <?php selected(in_array('notice', $notifier_query_loglevels), true) ?> value="notice" data-color="#DBDBB7"><?php echo $this->sh->getLogLevelTranslated('Notice') ?></option>
                            <option <?php selected(in_array('warning', $notifier_query_loglevels), true) ?> value="warning" data-color="#F7D358"><?php echo $this->sh->getLogLevelTranslated('Warning') ?></option>
                            <option <?php selected(in_array('error', $notifier_query_loglevels), true) ?> value="error" data-color="#F79F81"><?php echo $this->sh->getLogLevelTranslated('Error') ?></option>
                            <option <?php selected(in_array('critical', $notifier_query_loglevels), true) ?> value="critical" data-color="#FA5858"><?php echo $this->sh->getLogLevelTranslated('Critical') ?></option>
                            <option <?php selected(in_array('alert', $notifier_query_loglevels), true) ?> value="alert" data-color="#C74545"><?php echo $this->sh->getLogLevelTranslated('Alert') ?></option>
                            <option <?php selected(in_array('emergency', $notifier_query_loglevels), true) ?> value="emergency" data-color="#DF0101"><?php echo $this->sh->getLogLevelTranslated('Emergency') ?></option>
                        </select>
                    </p>
                </td></tr><tr<?php echo $class ?>><th scope="row"><label for="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>query_vars_messages"><?php _e('Message types', 'simple-history') ?></label></th><td>
                    <p>
                        <select id="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>query_vars_messages" name="<?php echo SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX ?>query_vars[messages][]" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--logger regular-text" placeholder="<?php esc_attr_e('All messages', 'simple-history') ?>" multiple><?php foreach ($messages_field_options as $logger_label => $logger_messages) : ?>

                            <optgroup label="<?php echo esc_attr($logger_label) ?>"><?php foreach ($logger_messages as $message_label => $message_value) : ?>

                                <option <?php selected(in_array($message_value, $notifier_query_messages), true) ?> value="<?php echo esc_attr($message_value) ?>"><?php echo $message_label ?></option><?php endforeach; ?>

                            </optgroup><?php endforeach; ?>

                        </select>
                    </p>
                <?php ?>
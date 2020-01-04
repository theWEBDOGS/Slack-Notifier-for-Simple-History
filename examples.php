<?php

exit;


/**
 * Settings tab will not be available in admin if is_public() returns false.
 * When settings are not public, saved settings will be disabled and only notifiers
 * included using the 'simple_history/slack_notifier/notifiers' filter will be used.
 *
 * @param		boolean	$public
 * @return		boolean
 */
add_filter('simple_history/slack_notifier/public', function(bool $public = true) {

	$public = false;

	return $public;

}, 10, 1);


/**
 * Ensure the Slack Notifier dropin is enabled.
 * This filter has no effect when is_public() returns false.
 *
 * @param		boolean	$enabled
 * @return		boolean
 */
add_filter('simple_history/slack_notifier/enabled', function(bool $enabled = null) {

	$enabled = true;

	return $enabled;

}, 10, 1);


/**
 * Push additional notifier option sets used to classify logged events for notification.
 *
 * @param		array	$notifiers
 * @return		array
 */
add_filter('simple_history/slack_notifier/notifiers', function(array $notifiers = []) {

    $notifiers[] = [

        /**
         * @var		boolean		enabled
         */
        'enabled' => true,

        /**
         * @var		string		webhook url
         */
        'webhook_url' => 'https://hooks.slack.com/services/ABC123/B123456/O4j88fj33bbbbjjkkfssd',

        /**
         * @var		string		delay
         * @uses	strtotime	string to time
         */
        'delay' => '+1 minute',

        /**
         * @var		array		log levels
         */
        'loglevels' => [ 'info', 'alert', ],

        /**
         * @var		array		messages
         */
        'messages' =>  [
            'SimpleOptionsLogger' => [
                'option_updated',
            ],
            'SimplePluginLogger'  => [
                'plugin_activated',
                'plugin_deactivated',
                'plugin_disabled_because_error',
                'plugin_installed',
                'plugin_installed_failed',
                'plugin_updated',
                'plugin_bulk_updated',
                'plugin_update_failed',
                'plugin_deleted',
            ],
            'SimpleThemeLogger'   => [
                'theme_updated',
                'theme_deleted',
                'theme_installed',
                'theme_switched',
                'appearance_customized',
                'widget_added',
                'widget_removed',
                'widget_order_changed',
                'widget_edited',
                'custom_background_changed',
            ],
        ],

    ];

    // return altered notifiers
    return $notifiers;

}, 10, 1);


/**
 * Override whether to use a notifier to classify the current event for scheduling.
 *
 * @param		boolean	$do_notifier
 * @param		array	$settings
 * @param		string	$level
 * @param		array	$context
 * @param		object	$logger
 *
 * @return		boolean
 */
add_filter('simple_history/slack_notifier/do_notifier', function($do_notifier, $settings, $level, $context, $logger) {

    if ($do_notifier && $level === 'info') {
        if (empty($settings['loglevels']) || in_array($level, $settings['loglevels'])) {
            if (empty($settings['messages']) || (isset($settings['messages']['SimpleOptionsLogger']) && in_array('option_updated', $settings['messages']['SimpleOptionsLogger']))) {
                if (is_object($logger) && isset($logger->slug) && $logger->slug === 'SimpleOptionsLogger') {
                    if (is_array($context) && isset($context['option']) && $context['option'] === 'cron') {
                        $do_notifier = false;
                    }
                }
            }
        }
    }

    // return altered do_notifier
    return $do_notifier;

}, 10, 5);


/**
 * Disable notification of any Simple User Logger activity.
 *
 * @param	boolean $do_notifier
 * @return      boolean
 */
add_filter('simple_history/slack_notifier/do_notifier/SimpleUserLogger', '__return_false');


/**
 * Disable notification of Simple Post Logger: updated post activity.
 *
 * @param	boolean $do_notifier
 * @return      boolean
 */
add_filter('simple_history/slack_notifier/do_notifier/SimplePostLogger/post_updated', '__return_false');


/**
 * Avoid using this filter...
 * Override the Slack Notifier public saved settings used to classify logged events for notification.
 *
 * @param	array	$settings
 * @return      array
 */
add_filter('simple_history/slack_notifier/saved_settings', function( array $settings = [ 'enabled' => false, 'webhook_url' => '', 'delay' => '+30 seconds', 'messages' => [], 'loglevels' => [] ]) {


    // Bypass filter.
    return $settings;


    // Check and set webhook URL
    $webhook_url = 'https://callback.url/here';

    // For example:
    // only override if prior settings <DO NOT EXIST>.
    if (empty($settings['loglevels'])) {
        $settings["webhook_url"] = $webhook_url;
    }


    // Check and set log levels to include
    $level = 'warn';

    // For example:
    // only override if prior settings <DO EXISTS>.
    if (!empty($settings['loglevels'])) {
        if (!in_array($level, $settings['loglevels'])) {
            $settings['loglevels'][] = $level;
        }
    }


    // Remove a specific logger message
    $remove_logger         = 'SimpleOptionsLogger';
    $remove_logger_message = 'option_updated';

    // Add a specific logger and message
    $new_logger            = 'SimpleThemeLogger';
    $new_logger_message    = 'theme_switched';

    // Check and change specific loggers and messages
    $new_logger_messages   = [
        'theme_updated',
        'theme_deleted',
        'theme_installed',
        'theme_switched',
        'appearance_customized',
        'widget_added',
        'widget_removed',
        'widget_order_changed',
        'widget_edited',
        'custom_background_changed',
    ];

    // For example:
    // add logger if prior settings <DO NOT EXIST>
    if (!isset($settings['messages'][$new_logger])) {
        $settings['messages'][$new_logger] = [$new_logger_message];
    }

    // For example:
    // update values if prior settings <DO EXIST>
    if (isset($settings['messages'][$remove_logger])) {

        // check for logger message and change settings
        if (in_array($remove_logger_message, $settings['messages'][$remove_logger])) {

            // use new logger messages
            $settings['messages'][$new_logger] = $new_logger_messages;

            // remove specific logger message
            $remove_logger_message_index = array_search($remove_logger_message, $settings['messages'][$remove_logger]);
            unset($settings['messages'][$remove_logger][$remove_logger_message_index]);
        }
    }


    // return altered settings
    return $settings;

}, 10, 1);

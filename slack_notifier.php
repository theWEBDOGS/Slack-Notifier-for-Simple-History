<?php

/**
 * Plugin Name: Slack Notifier for Simple History
 * Plugin URI: https://github.com/theWEBDOGS/Slack-Notifier-for-Simple-History
 * GitHub URI: https://github.com/theWEBDOGS/Slack-Notifier-for-Simple-History
 * Description: Send notifications for specific log events using a Slack webhook URL.
 * Version: 0.0.2
 * Author: WEBDOGS
 */

if (version_compare(phpversion(), '5.4', '>=')) {

    if (!defined('SIMPLE_HISTORY_SLACK_NOTIFIER_PATH')) {
        define('SIMPLE_HISTORY_SLACK_NOTIFIER_PATH', plugin_dir_path(__FILE__));
    }

    if (!defined('SIMPLE_HISTORY_SLACK_NOTIFIER_URL')) {
        define('SIMPLE_HISTORY_SLACK_NOTIFIER_URL', plugin_dir_url(__FILE__));
    }

    include_once __DIR__ . '/inc/helpers.php';

    include_once __DIR__ . '/dropins/SimpleHistory_SlackNotifierDropin.php';

    /**
     * Init plugin using the add_custom_dropin filter
     */
    function SimpleHistory_SlackNotifierDropin_addCustomDropin($simpleHistory)
    {
        $simpleHistory->register_dropin('SimpleHistory_SlackNotifierDropin');

    }

    add_action('simple_history/add_custom_dropin', 'SimpleHistory_SlackNotifierDropin_addCustomDropin');

    add_action('simple_history/slack_notifier/notify_slack', ['SimpleHistory_SlackNotifierDropin', 'notify_slack']);

    /**
     * Fallback if Simple History is not installed
     * Show message about it
     */
    add_action('admin_init', function () {

        if (!is_plugin_active('simple-history/index.php')) {

            add_action('admin_notices', function () {

                ?>
                <div class="updated error">
                    <p><?php _e('"Slack Notifier for Simple History" requires that the plugin "Simple History" is installed and activated.', 'simple-history'); ?></p>
                </div>
                <?php

            });
        }
    });

    register_activation_hook(__FILE__, function($network_wide) {

        $notifier = new SimpleHistory_SlackNotifierDropin();
        $settings = $notifier->get_settings();
        $defaults = function () use($settings) {

            foreach($settings as $setting) {
                $option = SimpleHistory_SlackNotifierDropin::SETTINGS_OPTION_PREFIX . $setting['name'];

                if (is_null(get_option($option))) {
                    set_option($option, $setting['setting_args']['default']);
                }
            }
        };

        // Check if the plugin
        // is network-activated.
        if ($network_wide) {

            $site_ids = get_sites([
                'fields'     => 'ids',
                'network_id' => get_current_network_id(),
            ]);

            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                $defaults();
                restore_current_blog();
            }

        } else {
            $defaults();
        }
    });


} else {

    // not ok
    // user is running to old version of php, add admin notice about that
    add_action('admin_notices', 'SimpleHistory_SlackNotifierDropin_oldVersionNotice');

    function SimpleHistory_SlackNotifierDropin_oldVersionNotice()
    {
        ?>
        <div class="updated error">
            <p><?php printf( __('Slack Notifier for Simple History requires at least PHP 5.4 (you have version %s).', 'simple-history'), phpversion() ); ?></p>
        </div>
        <?php

    }
}

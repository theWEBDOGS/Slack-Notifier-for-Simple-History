<?php

/**
 * Copyright 2019  Jacob Vega/Canote (email: jvcanote@gmail.com)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


if (!defined('WPINC')) {
    die();
}

/**
 * Simple History Slack notifications drop-in
 */
class SimpleHistory_SlackNotifierDropin
{

    // Simple History instance
    private $sh;



    /** Slug for the plugin */
    const NAME = 'Slack Notifier';

    /** Slug for the plugin */
    const SLUG = 'slack_notifier';



    /** File url for the plugin */
    const FILE_URL = SIMPLE_HISTORY_SLACK_NOTIFIER_URL;

    /** File path for the plugin */
    const FILE_PATH = SIMPLE_HISTORY_SLACK_NOTIFIER_PATH;



    /** ID for the general settings section */
    const FILTER_HOOK_PREFIX = SimpleHistory::DBTABLE . '/' . Self::SLUG . '/';

    /** ID for the general settings section */
    const SETTINGS_OPTION_PREFIX = SimpleHistory::DBTABLE . '_' . Self::SLUG . '_';



    /** Slug for the settings menu */
    const SETTINGS_MENU_SLUG = SimpleHistory::SETTINGS_MENU_SLUG . '_' . Self::SLUG;

    /** ID for the general options group */
    const SETTINGS_GENERAL_OPTION_GROUP = SimpleHistory::SETTINGS_GENERAL_OPTION_GROUP . '_' . Self::SLUG;

    /** ID for the general settings section */
    const SETTINGS_SECTION_GENERAL_ID = SimpleHistory::SETTINGS_SECTION_GENERAL_ID . '_' . Self::SLUG;




    public function __construct($sh)
    {
        $this->sh = $sh;

        $this->init();
    }




    public function init()
    {
        // Check the status of the notifications.
        if (Self::has_notifiers()) {
            // Prio 100 so we run late and give other filters chance to run
            add_action('simple_history/log/inserted', [$this, 'on_log_inserted'], 100, 3);
        }

        if (is_admin()) {
            // Check if settings are public
            if (Self::is_public()) {
                add_action('init', [$this, 'add_settings_tab']);
                add_action('simple_history/enqueue_admin_scripts', [$this, 'enqueue_admin_scripts']);
            }

            add_action('admin_menu', [$this, 'add_settings'], 11);
        }
    }




    public function enqueue_admin_scripts()
    {
        // wp_enqueue_script(Self::SETTINGS_OPTION_PREFIX . 'dropin', $file_url . Self::SETTINGS_OPTION_PREFIX . 'dropin.js', array( 'jquery' ), SIMPLE_HISTORY_VERSION, true);
        wp_enqueue_style(Self::SETTINGS_OPTION_PREFIX . 'dropin', Self::FILE_URL . 'css/' . Self::SETTINGS_OPTION_PREFIX . 'dropin.css', null, SIMPLE_HISTORY_VERSION);
    }




    /**
     * Add tab for notifier.
     */
    public function add_settings_tab()
    {

        $this->sh->registerSettingsTab([
            'slug' => Self::SLUG,
            'name' => __('Notifier', 'simple-history'),
            'function' => [$this, 'settings_tab_output']
        ]);
    }




    /**
     * Add settings for the notifications.
     */
    public function add_settings()
    {

        // we register a setting to keep track of
        // the notifications status (enabled/disabled)
        register_setting(
            Self::SETTINGS_GENERAL_OPTION_GROUP,
            Self::SETTINGS_OPTION_PREFIX . 'enabled',
            [
                'type' => 'boolean',
                'description' => __('Notifier is active when checked.', 'simple-history'),
                'sanitize_callback' => 'boolval',
                'show_in_rest' => false,
                'default' => false,
            ]
        );

        register_setting(
            Self::SETTINGS_GENERAL_OPTION_GROUP,
            Self::SETTINGS_OPTION_PREFIX . 'webhook_url',
            [
                'type' => 'string',
                'description' => __('The webhook URL for posting the notifications.', 'simple-history'),
                'sanitize_callback' => 'sanitize_url',
                'show_in_rest' => false,
                'default' => '',
            ]
        );

        register_setting(
            Self::SETTINGS_GENERAL_OPTION_GROUP,
            Self::SETTINGS_OPTION_PREFIX . 'delay',
            [
                'type' => 'string',
                'description' => __('An amount of time to delay sending notifications.', 'simple-history'),
                'sanitize_callback' => [$this, 'sanitize_' . Self::SLUG . '_delay'],
                'show_in_rest' => false,
                'default' => '+30 seconds',
            ]
        );

        register_setting(
            Self::SETTINGS_GENERAL_OPTION_GROUP,
            Self::SETTINGS_OPTION_PREFIX . 'query_vars',
            [
                'type' => 'string',
                'description' => __('The query string used to filter which log occurrences will trigger the notifier.', 'simple-history'),
                'sanitize_callback' => 'serialize',
                'show_in_rest' => false,
                'default' => 'a:2:{s:9:"loglevels";a:0:{}s:8:"messages";a:0:{}}',
            ]
        );

        /**
         * Start new section for notifications
         */

        add_settings_section(
            Self::SETTINGS_SECTION_GENERAL_ID,
            _x('Slack notification settings', 'notifier settings headline', 'simple-history'),
            [$this, 'do_settings_section'],
            Self::SETTINGS_MENU_SLUG
        );

        $args = [];

        // If notifications is not activated
        // the other fields are hidden.
        if (!Self::is_enabled()) {
            $args['class'] = 'hidden';
        }

        // Enable/Disabled notifications
        add_settings_field(
            Self::SETTINGS_OPTION_PREFIX . 'enabled',
            __('Enable', 'simple-history'),
            [$this, Self::SLUG . '_enabled_settings_field'],
            Self::SETTINGS_MENU_SLUG,
            Self::SETTINGS_SECTION_GENERAL_ID,
            ['label_for' => Self::SETTINGS_OPTION_PREFIX . 'enabled']
        );

        // Notifications webhook
        add_settings_field(
            Self::SETTINGS_OPTION_PREFIX . 'webhook_url',
            __('WebHook', 'simple-history'),
            [$this, Self::SLUG . '_webhook_url_settings_field'],
            Self::SETTINGS_MENU_SLUG,
            Self::SETTINGS_SECTION_GENERAL_ID,
            $args + ['label_for' => Self::SETTINGS_OPTION_PREFIX . 'webhook_url']
        );

        // Delay before notifications
        add_settings_field(
            Self::SETTINGS_OPTION_PREFIX . 'delay',
            __('Send notification...', 'simple-history'),
            [$this, Self::SLUG . '_delay_settings_field'],
            Self::SETTINGS_MENU_SLUG,
            Self::SETTINGS_SECTION_GENERAL_ID,
            $args + ['label_for' => Self::SETTINGS_OPTION_PREFIX . 'delay']
        );

        // Notifications query vars
        add_settings_field(
            Self::SETTINGS_OPTION_PREFIX . 'query_vars',
            __('Log levels', 'simple-history'),
            [$this, Self::SLUG . '_query_vars_settings_field'],
            Self::SETTINGS_MENU_SLUG,
            Self::SETTINGS_SECTION_GENERAL_ID,
            $args + ['label_for' => Self::SETTINGS_OPTION_PREFIX . 'query_vars' . '_loglevels']
        );
    } // settings




    /**
     * Check if notifier settings are public or private.
     *
     * @return boolean public
     */
    protected static function is_public()
    {
        return apply_filters(Self::FILTER_HOOK_PREFIX . 'public', true);
    }




    /**
     * Check if enabled notifiers exist.
     *
     * @return boolean filtered
     */
    protected static function has_notifiers()
    { // error_log(print_r(Self::get_notifiers(), true));
        foreach (Self::get_notifiers() as $settings) {
            if (isset($settings['enabled']) || true === $settings['enabled']) {
                return true;
            }
        }
        return false;
    }




    /**
     * Check if notifications is enabled or disabled.
     *
     * @return boolean enabled
     */
    protected static function is_enabled()
    {
        return Self::is_public() ? apply_filters(Self::FILTER_HOOK_PREFIX . 'enabled', (bool) get_option(Self::SETTINGS_OPTION_PREFIX . 'enabled')) : false;
    }




    /**
     * Get the webhook URL.
     *
     * @return string URL
     */
    protected static function get_webhook_url()
    {
        return get_option(Self::SETTINGS_OPTION_PREFIX . 'webhook_url');
    }




    /**
     * Get the delay interval.
     *
     * @return string duration
     * @example      '+420 minutes'
     * @uses          strtotime
     */
    protected static function get_delay()
    {
        return get_option(Self::SETTINGS_OPTION_PREFIX . 'delay');
    }




    /**
     * Get the query vars.
     *
     * @return array params for SimpleHistoryLogQuery::query().
     */
    protected static function get_query_vars(string $var = null)
    {
        $notifier_query_vars = get_option(Self::SETTINGS_OPTION_PREFIX . 'query_vars');
        $notifier_query_vars = unserialize($notifier_query_vars);
        $notifier_query_vars = wp_parse_args($notifier_query_vars, [
            'loglevels' => [],
            'messages'  => [],
        ]);

        if (isset($var, $notifier_query_vars[$var])) {
            return $notifier_query_vars[$var];
        }

        return $notifier_query_vars;
    }




    /**
     * Get all settings.
     *
     * @return array settings
     */
    protected static function get_saved_settings()
    {
        $settings = [
            'enabled'     => Self::is_enabled(),
            'webhook_url' => Self::get_webhook_url(),
            'delay'       => Self::get_delay(),
            'loglevels'   => Self::get_query_vars('loglevels'),
            'messages'    => Self::get_query_vars('messages'),
        ];

        foreach (['loglevels', 'messages'] as $setting) {
            if (!empty($settings[$setting])) {

                // Array with settings
                $arr_setting = [];

                // Tranform from stored format to our own internal format
                foreach ((array) $settings[$setting] as $one_arr_row) {
                    $arr_row = explode(',', $one_arr_row);
                    foreach ($arr_row as $one_row) {
                        if (false !== strstr($one_row, ':')) {
                            $arr_one = explode(':', $one_row);
                            if (!isset($arr_setting[$arr_one[0]])) {
                                $arr_setting[$arr_one[0]] = [];
                            }
                            $arr_setting[$arr_one[0]][] = $arr_one[1];
                        } else {
                            $arr_setting[] = $one_row;
                        }
                    }
                }
                $settings[$setting] = $arr_setting;
            }
        }

        return apply_filters(Self::FILTER_HOOK_PREFIX . 'saved_settings', $settings);
    }




    /**
     * Get notifiers settings.
     *
     * @return array notifiers
     */
    protected static function get_notifiers()
    {
        /**
         * Applied filter:
         *  'simple_history/slack_notifier/saved_settings'
         */
        $saved_settings = Self::get_saved_settings();

        /**
         * Filter notifiers to include additional option sets.
         */
        return apply_filters(
            Self::FILTER_HOOK_PREFIX . 'notifiers',
            [$saved_settings]
        );
    }




    /**
     * Sanitize delay settings
     */
    public function sanitize_slack_notifier_delay($field)
    {
        return !empty($field) && is_string($field) && in_array($field, ['+10 seconds', '+30 seconds', '+1 minute', '+5 minutes', '+30 minutes', '+1 hour', '+1 day']) ? $field : '+30 seconds';
    }




    /**
     * Output for settings tab.
     */
    public function settings_tab_output()
    {
        include Self::FILE_PATH . 'templates/settings.php';
    }




    public function do_settings_section()
    {
        /**
         * Settings section intro.
         */
    ?>

        <p>
            <?php _e('Posts logged events to a Slack channel.', 'simple-history') ?>
            <?php _e('Only share with trusted channels. Notices can contain sensitive or confidential information.', 'simple-history') ?>
        </p>

    <?php

    }




    public function slack_notifier_enabled_settings_field()
    {
        /**
         * Enable notifier.
         *
         * @param boolean enabled.
         */
        $notifier_enabled = Self::is_enabled();
    ?>

        <input <?php checked($notifier_enabled, true) ?> value="1" type="checkbox" id="<?php echo Self::SETTINGS_OPTION_PREFIX ?>enabled" name="<?php echo Self::SETTINGS_OPTION_PREFIX ?>enabled" onchange="jQuery(':input',jQuery(this).parents('.form-table')).not(this).parents('tr').toggleClass('hidden',!jQuery(this).is(':checked'))" />
        <label for="<?php echo Self::SETTINGS_OPTION_PREFIX ?>enabled">
            <?php _e('Enable Slack notifications', 'simple-history') ?>
        </label>

    <?php

    }




    public function slack_notifier_webhook_url_settings_field()
    {
        /**
         * Notification webhook URL.
         *
         * @param string URL.
         */
        $notifier_webhook_url = Self::get_webhook_url();
    ?>

        <p>
            <input id="<?php echo Self::SETTINGS_OPTION_PREFIX ?>webhook_url" type="url" class="regular-text ltr" placeholder="<?php esc_attr_e('https://hooks.slack.com/services/ABC123/B123456/O4j88fj33bbbbjjkkfssd', 'simple-history') ?>" name="<?php echo Self::SETTINGS_OPTION_PREFIX ?>webhook_url" value="<?php echo esc_attr($notifier_webhook_url) ?>" />
        </p>

        <p class="description">
            <small><?php printf(__('Create an %1$sincoming WebHook%2$s and then paste the WebHook URL here.', 'simple-history'), '<a href="https://my.slack.com/services/new/incoming-webhook/">', '</a>') ?></small>
        </p>

    <?php

    }




    public function slack_notifier_delay_settings_field()
    {
        /**
         * Notification delay duration.
         *
         * @param string delay.
         */
        $notifier_delay = Self::get_delay();
    ?>

        <p>
            <select id="<?php echo Self::SETTINGS_OPTION_PREFIX ?>delay" name="<?php echo Self::SETTINGS_OPTION_PREFIX ?>delay" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--date regular-text" placeholder="<?php _e('No delay', 'simple-history') ?>">
                <option <?php selected($notifier_delay, '+10 seconds') ?> value="+10 seconds"><?php _ex('After 10 seconds', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+30 seconds') ?> value="+30 seconds"><?php _ex('After 30 seconds', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+1 minute') ?> value="+1 minute"><?php _ex('After 1 minute', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+5 minutes') ?> value="+5 minutes"><?php _ex('After 5 minutes', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+30 minutes') ?> value="+30 minutes"><?php _ex('After 30 minutes', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+1 hour') ?> value="+1 hour"><?php _ex('After an hour', 'Option duration', 'simple-history') ?></option>
                <option <?php selected($notifier_delay, '+1 day') ?> value="+1 day"><?php _ex('After a day', 'Option duration', 'simple-history') ?></option>
            </select>
        </p>

    <?php

    }




    public function slack_notifier_query_vars_settings_field($args)
    {
        /**
         * Filter to control what the default loglevels are.
         *
         * @param array Array with loglevel sugs. Default empty = show all.
         */
        $notifier_query_loglevels = Self::get_query_vars('loglevels');
    ?>

        <p>
            <select id="<?php echo Self::SETTINGS_OPTION_PREFIX ?>query_vars_loglevels" name="<?php echo Self::SETTINGS_OPTION_PREFIX ?>query_vars[loglevels][]" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--loglevel regular-text" placeholder="<?php _e('All log levels', 'simple-history') ?>" multiple>
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
        </td>
        </tr>
        <tr class="<?php echo esc_attr($args['class']) ?>">
            <th scope="row">
                <label for="<?php echo Self::SETTINGS_OPTION_PREFIX ?>query_vars_messages">
                    <?php _e('Message types', 'simple-history') ?>
                </label>
            </th>
            <td>

                <?php
                $loggers_user_can_read = $this->sh->getLoggersThatUserCanRead();

                /**
                 * Notifier query messages.
                 *
                 * Message are in format: LoggerSlug:MessageKey
                 * For example:
                 *  - SimplePluginLogger:plugin_activated
                 *  - SimpleCommentsLogger:user_comment_added
                 *
                 * @param array Array with log message slugs. Default empty = show all.
                 */
                $notifier_query_messages = Self::get_query_vars('messages');
                ?>

                <p>
                    <select id="<?php echo Self::SETTINGS_OPTION_PREFIX ?>query_vars_messages" name="<?php echo Self::SETTINGS_OPTION_PREFIX ?>query_vars[messages][]" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--logger regular-text" placeholder="<?php _e('All messages', 'simple-history') ?>" multiple>

                        <?php
                        foreach ($loggers_user_can_read as $logger) {
                            $logger_info = $logger['instance']->getInfo();
                            $logger_slug = $logger['instance']->slug;

                            // Get labels for logger
                            if (isset($logger_info['labels']['search'])) {
                                printf(
                                    '<optgroup label="%1$s">',
                                    esc_attr($logger_info['labels']['search']['label'])
                                );

                                // If all activity
                                if (!empty($logger_info['labels']['search']['label_all'])) {
                                    $arr_all_search_messages = array();
                                    foreach ($logger_info['labels']['search']['options'] as $option_key => $option_messages) {
                                        $arr_all_search_messages = array_merge($arr_all_search_messages, $option_messages);
                                    }

                                    foreach ($arr_all_search_messages as $key => $val) {
                                        $arr_all_search_messages[$key] = $logger_slug . ':' . $val;
                                    }

                                    printf(
                                        '<option value="%2$s"%3$s>%1$s</option>',
                                        esc_attr($logger_info['labels']['search']['label_all']), // 1
                                        esc_attr(implode(',', $arr_all_search_messages)), // 2
                                        selected(in_array(implode(',', $arr_all_search_messages), $notifier_query_messages), true, false) // 3
                                    );
                                }

                                // For each specific search option
                                foreach ($logger_info['labels']['search']['options'] as $option_key => $option_messages) {
                                    foreach ($option_messages as $key => $val) {
                                        $option_messages[$key] = $logger_slug . ':' . $val;
                                    }

                                    $str_option_messages = implode(',', $option_messages);
                                    printf(
                                        '<option value="%2$s"%3$s>%1$s</option>',
                                        esc_attr($option_key), // 1
                                        esc_attr($str_option_messages), // 2
                                        selected(in_array($str_option_messages, $notifier_query_messages), true, false) // 3
                                    );
                                }

                                printf('</optgroup>');
                            } // End if().
                        } // End foreach().
                        ?>
                    </select>
                </p>

        <?php

    }




    public function on_log_inserted($context = [], $data = [], $logger)
    {
        $logger_slug = isset($logger->slug)            ? $logger->slug            : null;
        $message_key = isset($context['_message_key']) ? $context['_message_key'] : null;
        $level       = isset($data['level'])           ? $data['level']           : null;


        // Skip messages from HTTP_logger
        // to avoid infinite loop.
        if ($logger_slug === 'HTTP_Logger') {
            return;
        }


        // Skip messages from Simple Options Logger if $context['option'] = 'cron'
        // to avoid infinite loop.
        if ($logger_slug === 'SimpleOptionsLogger' && isset($context['option']) && $context['option'] === 'cron') {
            return;
        }


        /**
         * Filtered notifiers.
         *
         * @var	array	$notifiers	notifier option sets
         */
        $notifiers = Self::get_notifiers();


        foreach ($notifiers as $settings) {


            // Bail if webhook URL is not set or invalid.
            if (!isset($settings['webhook_url']) || empty($settings['webhook_url']) || !wp_http_validate_url($settings['webhook_url'])) {
                continue;
            }


            // Log levels are specified?
            if (!empty($settings['loglevels'])) {

                // Bail if no level.
                if (empty($level)) {
                    continue;

                // Bail if level not in set.
                } elseif (!in_array($level, $settings['loglevels'])) {
                    continue;
                }

                // Current level is in settings.
            }


            // Messages are specified?
            if (!empty($settings['messages'])) {

                // Bail if no logger slug or message key.
                if (empty($logger_slug) || empty($message_key)) {
                    continue;

                // Bail if current logger not in set.
                } elseif (!isset($settings['messages'][$logger_slug])) {
                    continue;

                // Bail if message key not in set.
                } elseif (!in_array($message_key, $settings['messages'][$logger_slug])) {
                    continue;
                }

                // Current logger and message are in settings.
            }


            $since_id = $logger->lastInsertID;

            // Bail if last Id is not set.
            if (!is_numeric($since_id) || empty($since_id)) {
                continue;
            }

            // Decrement Id number so the occurrence
            // will be included in the results.
            --$since_id;


            /**
             * Enabled.
             *
             * @var	boolean	$settings['enabled']
             */
            $enabled = isset($settings['enabled']) && true === $settings['enabled'];


            /**
             * Filter that makes it possible to shortcut this notification.
             * Return bool false to cancel.
             *
             * @var	boolean	$do_notifier
             */
            $do_notifier = apply_filters(
                Self::FILTER_HOOK_PREFIX . 'do_notifier',
                $enabled,
                $settings,
                $level,
                $context,
                $logger
            );
            if (false === $do_notifier) {
                continue;
            }


            /**
             * Easy shortcut method to disable notification of messages from a specific logger.
             *
             * Example filter name:
             * simple_history/slack_notifier/do_notifier/SimpleUserLogger
             * simple_history/slack_notifier/do_notifier/SimplePostLogger
             *
             * Example to disable notification of any user login/logout/failed login activity:
             * add_filter('simple_history/slack_notifier/do_notifier/SimpleUserLogger', '__return_false')
             *
             * @var	boolean	$do_notifier
             */
            $do_notifier = apply_filters(
                Self::FILTER_HOOK_PREFIX . "do_notifier/{$logger_slug}",
                $enabled
            );
            if (false === $do_notifier) {
                continue;
            }


            /**
             * Easy shortcut method to disable notification of messages from a specific logger and message.
             *
             * Example filter name:
             * simple_history/slack_notifier/do_notifier/SimpleUserLogger/user_logged_in
             * simple_history/slack_notifier/do_notifier/SimplePostLogger/post_updated
             *
             * @var	boolean	$do_notifier
             */
            $do_notifier = apply_filters(
                Self::FILTER_HOOK_PREFIX . "do_notifier/{$logger_slug}/{$message_key}",
                $enabled
            );
            if (false === $do_notifier) {
                continue;
            }


            $loglevels = !is_array($settings['loglevels']) || empty($settings['loglevels']) ? null : $settings['loglevels'];
            $messages  = !is_array($settings['messages'])  || empty($settings['messages'])  ? null : array_reduce(
                array_keys($settings['messages']),
                function ($messages, $logger) use ($settings) {
                    $logger_messages = [];
                    foreach ($settings['messages'][$logger] as $message) {
                        $logger_messages[] = $logger . ':' . $message;
                    }
                    $messages[] = implode(',', $logger_messages);
                    return $messages;
                },
                []
            );


            // Log query arguments.
            $log_query_args = [
                'type'           => 'overview',
                'format'         => '',
                'posts_per_page' => '',
                'paged'          => '',
                'since_id'       => $since_id,
                'loglevels'      => $loglevels,
                'messages'       => $messages,
                'SimpleHistoryLogQuery-showDebug' => 0,
            ];

            // Encode log query arguments.
            $log_query_args = base64_encode(serialize($log_query_args));


            $callback          = Self::FILTER_HOOK_PREFIX . 'notify_slack';
            $transient_key     = 'sh_' . array_md5($settings, null, $callback . '_2');


            if (false !== ($stored_query_args = get_transient($transient_key))) {

                // Use stored log query arguments.
                $log_query_args = $stored_query_args;

                // Clear previous transient and cron.
                delete_transient($transient_key);
                wp_clear_scheduled_hook($callback, [$transient_key]);
            }


            // Generate Unix timestamp for delay.
            $delay = strtotime($settings['delay']);

            // Store log query arguments.
            set_transient($transient_key, $log_query_args, $delay);

            // Schedule notification cron.
            wp_schedule_single_event($delay, $callback, [$transient_key]);
        }
    }




    /**
     * Notify Slack.
     *
     * Based on Slackit::on_log_insert_context_slackit()
     * Plugin:  Developer Loggers for Simple History
     * Author:  Pär Thernström
     *
     * @see https://github.com/bonny/Developer-Loggers-for-Simple-History/blob/master/loggers/Slackit.php   Post to Slack yo...
     * @see Slackit::on_log_insert_context_slackit()                                                        Original class method
     *
     * @param string $current_transient_key
     */
    public static function notify_slack(string $current_transient_key)
    {
        // Bail if no Simple History class.
        if (!class_exists('SimpleHistory')) {
            return;
        }


        /**
         * Singleton intance of SimpleHistory
         *
         * @var	SimpleHistory	$simpleHistory	SimpleHistory instance
         */
        $simpleHistory = SimpleHistory::get_instance();


        /**
         * Filtered notifiers.
         *
         * @var	array	$notifiers	notifier option sets
         */
        $notifiers = Self::get_notifiers();


        /**
         * Current action hook:
         * 'simple_history/slack_notifier/notify_slack'
         *
         * @var	string	$callback
         */
        $callback = current_action();


        // Remove output for "you"
        add_filter('simple_history/header_initiator_use_you', '__return_false');
        add_filter('simple_history/user_logger/plain_text_output_use_you', '__return_false');

        // Remove the date part of the section, because slack also shows date
        add_filter('simple_history/row_header_date_output', '__return_empty_string');

        // WP Cron can read single loggers
        add_filter('simple_history/loggers_user_can_read/can_read_single_logger', '__return_true');


        foreach ($notifiers as $settings) {

            $log_query_args    = '';


            $transient_key     = 'sh_' . array_md5($settings, null, $callback . '_2');
            $slack_webhook_url = isset($settings['webhook_url']) ? $settings['webhook_url'] : null;


            // Bail if no webhook URL.
            if (empty($slack_webhook_url)) {
                continue;

            // Bail if keys do not match.
            } elseif ($current_transient_key !== $transient_key) {
                continue;

            // Bail if no transient.
            } elseif (false === ($stored_query_args = get_transient($transient_key))) {
                continue;
            }


            // Clear transient
            delete_transient($transient_key);


            // Valid stored log query arguments.
            $log_query_args = $stored_query_args;

            // Decode log query arguments.
            $log_query_args = unserialize(base64_decode($log_query_args));

            // Bail if empty or invalid log query arguments.
            if (empty($log_query_args) || !is_array($log_query_args)) {
                continue;
            }


            // Setup log query.
            $logQuery            = new SimpleHistoryLogQuery();

            // Get log results.
            $results             = $logQuery->query($log_query_args);
            $results['api_args'] = $log_query_args;


            $occurrences         = [];
            $log_rows            = array_reverse($results['log_rows']);


            foreach ($log_rows as $row) {

                $context     = $row->context;
                $level       = empty($row->level)     ? '' : $row->level;
                $initiator   = empty($row->initiator) ? '' : $row->initiator;

                $logger      = $simpleHistory->getInstantiatedLoggerBySlug($row->logger);
                $message     = SimpleLogger::interpolate($row->message, $context);

                // Skip log messages from Simple Options Logger if $context['option'] === 'cron'.
                if ($row->logger === 'SimpleOptionsLogger' && isset($context['option']) && $context['option'] === 'cron') {
                    continue;
                }

                $color       = '';

                switch ($level) {

                    case 'debug':
                        $color = '#CEF6D8';
                        break;

                    case 'info':
                        $color = 'white';
                        break;

                    case 'notice':
                        $color = '#FFFFE0';
                        break;

                    case 'warning':
                        $color = '#F7D358';
                        break;

                    case 'error':
                        $color = '#F79F81';
                        break;

                    case 'critical':
                        $color = '#FA5858';
                        break;

                    case 'alert':
                        $color = '#C74545';
                        break;

                    case 'emergency':
                        $color = '#610B0B';
                        break;
                }

                /* 
                $remote_addr = empty($context['_server_remote_addr']) ? '' : $context['_server_remote_addr'];
                $fields = [
                    [
                        'title' => 'Site',
                        'value' => home_url(),
                        'short' => true
                    ],
                    [
                        'title' => 'IP address',
                        'value' => sprintf('https://ipinfo.io/%s', $remote_addr),
                        'short' => true
                    ],
                ]; */

                /* 
                $fields = [];
                foreach ($context as $title => $value) {
                    $fields[] = [
                        'title' => $title,
                        'value' => $value,
                        'short' => true
                    ];
                } */

                // Event details.
                $header_html       = $logger->getLogRowHeaderOutput($row);
                $plain_text_html   = $logger->getLogRowPlainTextOutput($row);
                $sender_image      = '';
                $sender_image_html = '';
                $initiator_text    = '';


                switch ($initiator) {

                    case 'wp':
                        $initiator_text   .= 'WordPress';
                        $sender_image      = 'https://s.w.org/about/images/logos/wordpress-logo-32-blue.png';
                        break;

                    case 'wp_cli':
                        $initiator_text   .= 'WP-CLI';
                        $sender_image      = 'https://s.w.org/about/images/logos/wordpress-logo-32-blue.png';
                        break;

                    case 'wp_user':
                        $initiator_wp_user = trim(strip_tags($header_html));
                        $initiator_wp_user = str_replace("\n", ' ', $initiator_wp_user);
                        $initiator_text    = $initiator_wp_user;

                        // We need to post plain image to slack so just get it through a regexp.
                        // <img alt='' src='http://2.gravatar.com/avatar/e57939a1ce063c7619aceda8be6fe04b?s=32&#038;d=mm&#038;r=pg' srcset='http://2.gravatar.com/avatar/e57939a1ce063c7619aceda8be6fe04b?s=64&amp;d=mm&amp;r=pg 2x' class='avatar avatar-32 photo' height='32' width='32' />
                        $sender_image_html = $logger->getLogRowSenderImageOutput($row);
                        $image_arr         = json_decode(json_encode(simplexml_load_string($sender_image_html)), true);
                        $sender_image      = empty($image_arr['@attributes']['src']) ? '' : $image_arr['@attributes']['src'];
                        $sender_image      = str_replace('s=32', 's=64', $sender_image);
                        break;

                    case 'web_user':
                        $initiator_text   .= 'Anonymous web user';
                        $sender_image      = 'http://www.gravatar.com/avatar/00000000000000000000000000000000?d=mm&f=y';
                        break;

                    case 'other':
                        $initiator_text    = 'Other';
                        break;

                    default:
                        $initiator_text    = $initiator;
                        break;
                }

                $author_icon = '';
                // Use site icon as the thumb for this event
                if (function_exists('get_site_icon_url')) {
                    $author_icon = get_site_icon_url(512);
                }


                // Clear possible shitespace that is left,
                // because removed tags and newlines left etc.
                $initiator_text  = preg_replace('/\s+/', ' ', $initiator_text);
                $title           = html_entity_decode(strip_tags($plain_text_html));


                $item_permalink  = admin_url('index.php?page=simple_history_page');
                $item_permalink .= "#item/{$row->id}";


                $occurrences[] = [

                    'fallback'    => "{$initiator_text}: {$title}",

                    'author_name' => $initiator_text,
                    'author_icon' => $sender_image,

                    'title'       => $title,
                    'title_link'  => $item_permalink,

                    'footer'      => __('WordPress Simple History', 'simple-history'),
                    'footer_icon' => 'http://simple-history.s3-website.eu-central-1.amazonaws.com/images/simple-history-icon-32.png',

                    // An optional value that can either be one of good,
                    // warning, danger, or any hex color code (eg. #439FE0).
                    // This value is used to color the border along the
                    // left side of the message attachment.
                    'color'       => $color,

                    // 'text'        => $title,
                    // 'fields'      => $fields,
                    // 'pretext'     => "\n",
                    // 'thumb_url'   => $author_icon,
                    // 'author_link' => home_url(),
                ];
            }


            $total_occurrences = sizeof($occurrences);
            $occurrence_text   = sprintf(
                _n('%s new occurrence', '%s new occurrences', $total_occurrences, 'simple-history'),
                    number_format_i18n($total_occurrences)
            );

            $slack_text        = sprintf(
                __('%2$s ( %3$s ) reported %1$s.', 'simple-history'),
                    $occurrence_text, // 1
                    get_bloginfo('name', 'display'), // 2
                    home_url() // 3
            );

            $slack_mrkdwn      = sprintf(
                __("%2\$s ( %3\$s ) reported *%1\$s*.", 'simple-history'),
                    $occurrence_text, // 1
                    get_bloginfo('name', 'display'), // 2
                    home_url() // 3
            );

            $slack_data        = [
                'username'     => __('Notifier for WordPress', 'simple-history'),
                'icon_url'     => 'https://s.w.org/about/images/logos/wordpress-logo-32.png',
                'text'         => $slack_text,
                'blocks'       => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => $slack_mrkdwn,
                        ], /* 'accessory' => [ 'type' => 'button', 'text' => [ 'type' => 'plain_text', 'text' => 'Edit Notifier', 'emoji' => true, ], 'url' => admin_url(sprintf('options-general.php?page=%1$s&selected-tab=%2$s', SimpleHistory::SETTINGS_MENU_SLUG, Self::SLUG)), ], */
                    ],
                ],

                // Only use the 10 most recent occurrences.
                'attachments'  => array_slice($occurrences, 0, 10),
            ];

            $post_args         = [
                'blocking'     => false,
                'timeout'      => 0.01,
                'body'         => json_encode($slack_data),
            ];


            // Slack it!
            wp_remote_post($slack_webhook_url, $post_args);
        }

        // Remove filters
        remove_filter('simple_history/header_initiator_use_you', '__return_false');
        remove_filter('simple_history/user_logger/plain_text_output_use_you', '__return_false');
        remove_filter('simple_history/row_header_date_output', '__return_empty_string');
        remove_filter('simple_history/loggers_user_can_read/can_read_single_logger', '__return_true');
    }
} // end notifications class

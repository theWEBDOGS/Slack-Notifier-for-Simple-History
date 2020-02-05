<?php

/**
 * Copyright 2020  WEBDOGS LLC. (email: thedogs@webdogs.com)
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




    public function __construct($sh = null)
    {
        $this->sh = $sh and $this->init();
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
        wp_enqueue_style(Self::SETTINGS_OPTION_PREFIX . 'dropin', Self::FILE_URL . 'css/' . Self::SETTINGS_OPTION_PREFIX . 'dropin.css', null, SIMPLE_HISTORY_VERSION);         // wp_enqueue_script(Self::SETTINGS_OPTION_PREFIX . 'dropin', $file_url . Self::SETTINGS_OPTION_PREFIX . 'dropin.js', array( 'jquery' ), SIMPLE_HISTORY_VERSION, true);
    }




    /**
     * Add tab for notifier.
     */
    public function add_settings_tab()
    {
        $this->sh->registerSettingsTab([
            'slug' => Self::SLUG,
            'name' => __('Notifier', 'simple-history'),
            'function' => [$this, Self::SLUG . '_settings_tab_output']
        ]);
    }




    /**
     * Add settings for the notifications.
     */
    public function add_settings()
    {
        // Settings field parameters
        $settings = $this->get_settings();

        foreach($settings as $setting) {
            register_setting(
                Self::SETTINGS_GENERAL_OPTION_GROUP,
                Self::SETTINGS_OPTION_PREFIX . $setting['name'],
                $setting['setting_args']
            );
        }

        /**
         * Start new section for notifications
         */
        add_settings_section(
            Self::SETTINGS_SECTION_GENERAL_ID,
            _x('Slack notification settings', 'notifier settings headline', 'simple-history'),
            [$this, Self::SLUG . '_settings_section_output'],
            Self::SETTINGS_MENU_SLUG
        );

        $args = [];

        foreach($settings as $setting) {

            add_settings_field(
                Self::SETTINGS_OPTION_PREFIX . $setting['name'],
                $setting['title'],
                [$this, Self::SLUG . $setting['callback']],
                Self::SETTINGS_MENU_SLUG,
                Self::SETTINGS_SECTION_GENERAL_ID,
                $args + ['label_for' => Self::SETTINGS_OPTION_PREFIX . $setting['name']]
            );

            // If notifications is not activated
            // the other fields are hidden.
            if (!Self::is_enabled()) {
                $args['class'] = 'hidden';
            }

        }
    } // settings




    /**
     * Get settings API configuration parameters.
     *
     * @return array $settings basic values.
     */
    public function get_settings()
    {
        $setting = [

            // Enable/Disabled notifications

            [
                'name'         => 'enabled',
                'title'        => __('Enable', 'simple-history'),
                'callback'     => '_enabled_settings_field_output',
                'setting_args' => [
                    'type'              => 'boolean',
                    'description'       => __('Notifier is active when checked.', 'simple-history'),
                    'sanitize_callback' => 'boolval',
                    'show_in_rest'      => false,
                    'default'           => false,
                ],
            ],

            // Notifications webhook

            [
                'name'         => 'webhook_url',
                'title'        => __('WebHook', 'simple-history'),
                'callback'     => '_webhook_url_settings_field_output',
                'setting_args' => [
                    'type'              => 'string',
                    'description'       => __('The webhook URL for posting the notifications.', 'simple-history'),
                    'sanitize_callback' => 'sanitize_url',
                    'show_in_rest'      => false,
                    'default'           => '',
                ],
            ],

            // Delay before notifications

            [
                'name'         => 'delay',
                'title'        => __('Send notification...', 'simple-history'),
                'callback'     => '_delay_settings_field_output',
                'setting_args' => [
                    'type'              => 'string',
                    'description'       => __('An amount of time to delay sending notifications.', 'simple-history'),
                    'sanitize_callback' => [$this, 'sanitize_' . Self::SLUG . '_delay'],
                    'show_in_rest'      => false,
                    'default'           => '+30 seconds',
                ],
            ],

            // Notifications query vars

            [
                'name'         => 'query_vars',
                'title'        => __('Log levels', 'simple-history'),
                'callback'     => '_query_vars_settings_field_output',
                'setting_args' => [
                    'type'              => 'string',
                    'description'       => __('The query string used to filter which log occurrences will trigger the notifier.', 'simple-history'),
                    'sanitize_callback' => 'serialize',
                    'show_in_rest'      => false,
                    'default'           => 'a:2:{s:9:"loglevels";a:0:{}s:8:"messages";a:0:{}}',
                ],
            ],
        ];

        return $setting;
    }




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
         *  - 'simple_history/slack_notifier/saved_settings'
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
    public function slack_notifier_settings_tab_output()
    {
        include Self::FILE_PATH . 'templates/settings.php';
    }




    public function slack_notifier_settings_section_output()
    {
        include Self::FILE_PATH . 'templates/section.php';
    }




    public function slack_notifier_enabled_settings_field_output()
    {
        /**
         * Enable notifier.
         *
         * @param boolean enabled.
         */
        $notifier_enabled = Self::is_enabled(); // print_r($notifier_enabled);

        include Self::FILE_PATH . 'templates/enabled-field.php';
    }




    public function slack_notifier_webhook_url_settings_field_output()
    {
        /**
         * Notification webhook URL.
         *
         * @param string URL.
         */
        $notifier_webhook_url = Self::get_webhook_url(); // print_r($notifier_webhook_url);

        include Self::FILE_PATH . 'templates/webhook-url-field.php';
    }




    public function slack_notifier_delay_settings_field_output()
    {
        /**
         * Notification delay duration.
         *
         * @param string delay.
         */
        $notifier_delay = Self::get_delay(); // print_r($notifier_delay);

        include Self::FILE_PATH . 'templates/delay-field.php';
    }




    public function slack_notifier_query_vars_settings_field_output($args)
    {
        /**
         * Filter to control what the default loglevels are.
         *
         * @param array Array with loglevel sugs. Default empty = show all.
         */
        $notifier_query_loglevels = Self::get_query_vars('loglevels'); // print_r($notifier_query_loglevels);

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
        $notifier_query_messages = Self::get_query_vars('messages'); // print_r($notifier_query_messages);

        /**
         * Loggers the current user has access to read.
         *
         * @param array Array with loggers that user can read.
         */
        $loggers_user_can_read = $this->sh->getLoggersThatUserCanRead();

        include Self::FILE_PATH . 'templates/query-vars-fields.php';
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
            if (!isset($settings['webhook_url']) || !is_string($settings['webhook_url']) || !wp_http_validate_url($settings['webhook_url'])) {
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
             *  - simple_history/slack_notifier/do_notifier/SimpleUserLogger
             *  - simple_history/slack_notifier/do_notifier/SimplePostLogger
             *
             * Example to disable notification of any user login/logout/failed login activity:
             *  - add_filter('simple_history/slack_notifier/do_notifier/SimpleUserLogger', '__return_false')
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
             *  - simple_history/slack_notifier/do_notifier/SimpleUserLogger/user_logged_in
             *  - simple_history/slack_notifier/do_notifier/SimplePostLogger/post_updated
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
            $network_blog      = sprintf('%d_%d_', get_current_network_id(), get_current_blog_id());

            $transient_key     = 'sh_' . array_md5($settings, $network_blog, $callback . '_2');


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
         * Current network and blog ids.
         *
         * @var	string	$network_blog
         */
        $network_blog = sprintf('%d_%d_', get_current_network_id(), get_current_blog_id());


        /**
         * Current action hook:
         *  - 'simple_history/slack_notifier/notify_slack'
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


            $transient_key     = 'sh_' . array_md5($settings, $network_blog, $callback . '_2');
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

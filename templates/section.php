<?php
/**
 * The template for displaying a description of the settings section.
 *
 * @author  WEBDOGS
 * @package SimpleHistory/SlackNotifierDropin/Templates
 * @version 0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

    <p>
        <?php _e('Post logged events to a Slack channel.', 'simple-history') ?>
        <?php _e('Only share with trusted channels. Notices can contain sensitive or confidential information.', 'simple-history') ?>
    </p>

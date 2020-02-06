<?php
/**
 * The template for displaying a form with all the sections for a particular settings tab.
 *
 * @author  Jacob Vega/Canote
 * @package SimpleHistory/SlackNotifierDropin/Templates
 * @since   0.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

            <form method="post" action="options.php" class="SimpleHistory__filters__form SimpleHistory__filters">

                <?php // Prints out all settings sections added to a particular settings page
                do_settings_sections(SimpleHistory_SlackNotifierDropin::SETTINGS_MENU_SLUG); ?>

                <?php // Output nonce, action, and option_page fields
                settings_fields(SimpleHistory_SlackNotifierDropin::SETTINGS_GENERAL_OPTION_GROUP); ?>

                <?php submit_button(); ?>

            </form>

            <script type="text/javascript">
                (function($) {
                    $('body').addClass('SimpleHistory--isLoaded')
                })(jQuery);
            </script>
